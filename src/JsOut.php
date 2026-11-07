<?php
namespace Ufw;

/**
 * Concatenates/minifies JS from named placeholders (`jsReady`, `jsInline`, …) and writes the result under a
 * configurable storage path so it is served as a real file with a stable URL. That gives (a) reuse on the
 * server for later requests and (b) normal browser caching of the script for repeat visits.
 *
 * **`jsReady` scheduling:** by default, aggregated `jsReady` output is emitted as **`type="module"`** (deferred
 * by the browser; strict mode; no jQuery). Under **`jsOutStorage`**, optional **`readyWrapper`** may be **`classic`**
 * (IIFE + `text/javascript`), **`jquery`**, or **`none`**. The alias **`dom`** is treated as **`classic`**.
 *
 * **Minified bundle URL:** **`jsOutStorage.serve-minified`** chooses whether the emitted **`src`** uses the
 * **`.min.js`** suffix (same as before).
 *
 * @method static JsOut getInstance()
 */
class JsOut
{
    use Singleton;

    /**
     * @var string
     */
    protected $jsRemote = '';

    /**
     * @var array<string, string>
     */
    protected $jsContent = array();

    /**
     * @var string
     */
    protected $sig = '';

    /**
     * @var string
     */
    protected $fn = '';

    /**
     * @var bool
     */
    protected $doConcatenate = true;

    /**
     * @var bool
     */
    protected $_includeExt = false;

    /**
     * @var string
     */
    protected $storage = '/static/js/gc';

    /**
     * @var string|null
     */
    protected $storageAbsPath = null;

    /**
     * @return string
     */
    public function readyPlaceholder()
    {
        $config = \Ufw\Registry::getInstance()->getApplication()->getConfig();
        $cfg = $config['jsOutStorage'] ?? null;
        if (!\is_array($cfg) || !isset($cfg['webpath'], $cfg['absrootpath'])) {
            return $this->placeholderScriptsOnlyOutput();
        }

        $this->storage = is_string($cfg['webpath']) ? $cfg['webpath'] : $this->storage;
        $this->storageAbsPath = is_string($cfg['absrootpath']) ? $cfg['absrootpath'] : null;
        
        $this->jsContent['ready'] = $this->fetchPlaceholder('jsReady');
        $this->jsContent['ext'] = $this->fetchPlaceholder('jsExt');
        $this->jsContent['inline'] = $this->fetchPlaceholder('jsInline');
        $this->jsRemote = $this->fetchPlaceholder('jsRemote');
        
        if ($this->_alreadyExists()) {
            unset($this->jsContent['ready'], $this->jsContent['ext']);
            $this->_includeExt = true;
            $this->_concatenate(false)
                ->_sanitize()
                ->_minify();
        } else {
            $this->_concatenate(true)
                ->_sanitize()
                ->_dump('.js')
                ->_minify()
                ->_dump('.min.js')
                ->_gzip()
                ->_dump('.min.js.gz');
        }
        
        $ext = !empty($cfg['serve-minified']) ? '.min.js' : '.js';
        
        $bundleScriptType = $this->getReadyWrapperMode() === 'module' ? ' type="module"' : ' type="text/javascript"';

        return ($this->jsRemote ? $this->jsRemote : '') . ($this->jsContent['inline'] ? '<script type="text/javascript">' . $this->jsContent['inline'] . '</script>' : '') . ($this->_includeExt ? '<script' . $bundleScriptType . ' defer src="' . $this->fn . $ext . '"></script>' : '');
    }

    /**
     * @return bool
     */
    protected function _alreadyExists()
    {
        $this->sig = md5($this->jsContent['ready'] . '~' . $this->jsContent['ext']);
        $this->fn = "$this->storage/$this->sig";
        return file_exists($this->storageAbsPath . $this->fn . '.min.js');
    }

    /**
     * @param bool $val
     * @return $this
     */
    protected function _concatenate($val)
    {
        $this->doConcatenate = $val;
        return $this;
    }

    /**
     * @param string $ext
     * @return $this
     */
    protected function _dump($ext)
    {
        if ($this->jsContent['ext']) {
            $path = $this->storageAbsPath . $this->fn . $ext;
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($path, $this->jsContent['ext']);
            chmod($path, 0777);
            $this->_includeExt = true;
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function _gzip()
    {
        if ($this->jsContent['ext']) {
            $encoded = gzencode($this->jsContent['ext']);
            if ($encoded !== false) {
                $this->jsContent['ext'] = $encoded;
            }
        }
        return $this;
    }

    /**
     * {@link https://packagist.org/packages/tedivm/jshrink JShrink} - required via Composer; pure PHP minify for cached bundles.
     */
    /**
     * @return $this
     */
    protected function _minify()
    {
        foreach ($this->jsContent as $key => $value) {
            if (!\trim($value)) {
                continue;
            }
            $minified = \JShrink\Minifier::minify(\trim($value));
            if (is_string($minified)) {
                $this->jsContent[$key] = $minified;
            }
        }
        return $this;
    }

    /**
     * Normalize each `jsContent` string before concatenation/minify.
     *
     * In order:
     * 1. Trim leading whitespace at the start of each line, then trim trailing whitespace at the end of each line
     *    (multiline {@code m} modifier on both patterns).
     * 2. Replace every opening or closing script tag ({@code <script …>}, {@code </script>}) with {@code ;}. This is a
     *    best-effort unwrap for server-assembled fragments; it is not safe if the JS source itself contains those
     *    sequences inside a string literal, or if you embed {@code <script src="…">} (those tags would be stripped).
     */
    /**
     * @return $this
     */
    protected function _sanitize()
    {
        $this->jsContent = preg_replace(array(
            '~^\s+~m',
            '~\s+$~m',
            '~</?script\b[^>]*>~i',
        ), array(
            '',
            '',
            ';',
        ), $this->jsContent);
        if ($this->doConcatenate) {
            $wrapped = $this->jsContent['ready'] !== ''
                ? $this->wrapJsReadyFragments((string) $this->jsContent['ready'])
                : '';
            $this->jsContent['ext'] = $this->jsContent['ext'] . ($wrapped !== '' ? "\n;" . $wrapped : '');
            unset($this->jsContent['ready']);
        }
        return $this;
    }

    /**
     * How `jsReady` is packaged: {@code module} (default), {@code classic}, {@code jquery}, or {@code none}.
     *
     * Reads {@code $config['jsOutStorage']['readyWrapper']} when present; otherwise defaults to {@code module}.
     * The alias {@code dom} is treated the same as {@code classic}.
     */
    protected function getReadyWrapperMode(): string
    {
        try {
            $config = Registry::getInstance()->getApplication()->getConfig();
        } catch (\Throwable $e) {
            return 'module';
        }
        $storage = $config['jsOutStorage'] ?? null;
        $mode = 'module';
        if (is_array($storage) && array_key_exists('readyWrapper', $storage)) {
            $rawMode = $storage['readyWrapper'];
            if (is_string($rawMode) || is_int($rawMode) || is_float($rawMode)) {
                $mode = strtolower(trim((string) $rawMode));
            }
        }
        if ($mode === '') {
            $mode = 'module';
        }
        if ($mode === 'dom') {
            $mode = 'classic';
        }
        if (!in_array($mode, ['module', 'classic', 'jquery', 'none'], true)) {
            return 'module';
        }

        return $mode;
    }

    /**
     * `type` attribute value for the tag that carries aggregated `jsReady` output (inline path).
     */
    protected function getReadyScriptTypeAttribute(): string
    {
        return $this->getReadyWrapperMode() === 'module' ? ' type="module"' : ' type="text/javascript"';
    }

    /**
     * Wrap or pass through concatenated `jsReady` JavaScript (bundling may still use one file with `jsExt`).
     */
    protected function wrapJsReadyFragments(string $inner): string
    {
        $inner = trim($inner);
        if ($inner === '') {
            return '';
        }
        switch ($this->getReadyWrapperMode()) {
            case 'jquery':
                return 'jQuery(function($){' . $inner . '});';
            case 'none':
                return $inner;
            case 'classic':
                return '(function(){function __ufwDomReady(){' . $inner . '}'
                    . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",__ufwDomReady,{once:true});}'
                    . 'else{__ufwDomReady();}})();';
            case 'module':
            default:
                // Module scripts are deferred; no IIFE needed for DOM-safe timing.
                return $inner;
        }
    }

    /**
     * When jsOutStorage is missing from app config, skip concat/minify file output and emit placeholders only.
     */
    /**
     * @return string
     */
    protected function placeholderScriptsOnlyOutput()
    {
        $this->jsContent['ready'] = $this->fetchPlaceholder('jsReady');
        $this->jsContent['ext'] = $this->fetchPlaceholder('jsExt');
        $this->jsContent['inline'] = $this->fetchPlaceholder('jsInline');
        $this->jsRemote = $this->fetchPlaceholder('jsRemote');

        $out = $this->jsRemote ? $this->jsRemote : '';
        if ($this->jsContent['inline'] !== '') {
            $out .= '<script type="text/javascript">' . $this->jsContent['inline'] . '</script>';
        }
        if ($this->jsContent['ext'] !== '') {
            $out .= '<script type="text/javascript">' . $this->jsContent['ext'] . '</script>';
        }
        // `module`: browser defers execution; `classic`/`jquery`/`none`: classic script (see wrapJsReadyFragments).
        if ($this->jsContent['ready'] !== '') {
            $wrapped = $this->wrapJsReadyFragments((string) $this->jsContent['ready']);
            if ($wrapped !== '') {
                $out .= '<script' . $this->getReadyScriptTypeAttribute() . '>' . $wrapped . '</script>';
            }
        }

        return $out;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function fetchPlaceholder($name)
    {
        $helper = Registry::getInstance()->getApplication()->getHelper('placeholder');
        if (!is_callable($helper)) {
            return '';
        }
        $content = $helper($name);
        if ($content instanceof Placeholder) {
            return $content->__toString();
        }
        if (is_string($content)) {
            return $content;
        }
        if (is_int($content) || is_float($content) || is_bool($content)) {
            return (string) $content;
        }

        return '';
    }
}