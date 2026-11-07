<?php

namespace Ufw\Helper;

/**
 * Helpers that include PHP view scripts from the application tree with a fallback to framework `src/views/`.
 *
 * @method \Ufw\Helper\Html5Tag html5Tag()
 * @method string partial(string $script, array<string, mixed> $vars = array())
 * @method \Ufw\Placeholder placeholder(string $name)
 * @method string html5Attribs(array<int|string, mixed> $attribs)
 */
abstract class ViewRendererBase extends Base
{
    /**
     * @var string|null
     */
    protected static $fwViewsPath;

    /**
     * Absolute path to bundled framework views (`src/views`).
     *
     * @return string
     */
    protected function getFwViewsPath()
    {
        if (self::$fwViewsPath === null) {
            $path = realpath(__DIR__ . '/../views');
            self::$fwViewsPath = $path !== false ? $path : '';
        }
        return self::$fwViewsPath;
    }

    /**
     * @return string
     */
    protected function getScriptName()
    {
        return $this->getController()->getScriptName();
    }

    /**
     * Includes a view script: application `views/` first, then framework `src/views/`.
     *
     * @param array<string, mixed> $vars Variables passed into the script via {@see extract()}
     * @param string|null $script Relative path under `views/`; default is current action script name + `.php`
     * @param bool $controllerDir When true, prefix with the controller {@see \Ufw\Controller\Base::getIdent()}
     * @return string Captured output
     */
    protected function renderHtml($vars = array(), $script = null, $controllerDir = true)
    {
        ob_start();
        $c = $controllerDir ? $this->getController()->getIdent() : '';

        if (!$script) {
            $script = $this->getScriptName() . '.php';
        }
        if ($c) {
            $script = "$c/$script";
        }

        $appViews = \Ufw\Paths::application() . '/views/' . $script;
        $fwViews = $this->getFwViewsPath() . '/' . $script;
        if (file_exists($appViews)) {
            extract($vars);
            include $appViews;
        } elseif (file_exists($fwViews)) {
            extract($vars);
            include $fwViews;
        }

        $cleaned = ob_get_clean();
        return $cleaned === false ? '' : $cleaned;
    }
}
