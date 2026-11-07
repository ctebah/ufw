<?php
namespace Ufw\Helper;

/**
 * Named CSS/JS bundles from app config (`config/assets/{name}.php`, or `data/assets/` when serving minified
 * bundles). Invokable: `$this->assets('name')` then {@see css()} / {@see js()}.
 */
class Assets extends Base
{

    /**
     * @var \Ufw\Container<\Ufw\Utils\Asset\Css>|null
     */
    protected $cssSet = null;
    
    /**
     * @var \Ufw\Container<\Ufw\Utils\Asset\Js>|null
     */
    protected $jsSet = null;
    
    /**
     * @var string|null
     */
    protected $name = null;
    
    /**
     * @var array<string, array<string, mixed>>
     */
    protected $configs = array();

    /**
     * @param string $name
     * @return $this
     */
    public function __invoke($name)
    {
        $this->setName($name);
        return $this;
    }

    /**
     * @return \Ufw\Utils\Asset\Css
     */
    public function css()
    {
        $asset = $this->getCssSet()->get($this->getName());
        if (!$asset->isInitialized()) {
            $config = $this->getConfig();
            $cssConfig = isset($config['css']) && is_array($config['css']) ? $this->normalizeAssetInitData($config['css']) : array();
            $asset->init($cssConfig)->setApplication($this->getApplication());
        }
        return $asset;
    }
    
    /**
     * @param string $position
     * @return \Ufw\Utils\Asset\Js
     */
    public function js($position = \Ufw\Utils\Asset\Js::POS_BODY_BOTTOM) {
        $asset = $this->getJsSet()->get($this->getName() . '-' . $position);
        if (!$asset->isInitialized()) {
            $config = $this->getConfig();
            $jsConfig = isset($config['js']) && is_array($config['js']) ? $this->normalizeAssetInitData($config['js']) : array();
            $asset->init($jsConfig)->setPosition($position)->setApplication($this->getApplication());
        }
        return $asset;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return (string) $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * @return array<string, mixed>
     */
    protected function getConfig() {
        $name = $this->getName();
        if (!isset($this->configs[$name])) {
            $appConfig = $this->getApplication()->getConfig();
            $assetsConfig = is_array($appConfig) && isset($appConfig['assets']) && is_array($appConfig['assets'])
                ? $appConfig['assets']
                : array();
            $min = !empty($assetsConfig['serve-minified']);
            $this->configs[$name] = array();
            if ($min) {
                $loaded = include \Ufw\Paths::application() . '/data/assets/' . $name . '.php';
                $this->configs[$name] = is_array($loaded) ? $this->normalizeAssetInitData($loaded) : array();
            }
            if (!$this->configs[$name]) {
                $loaded = include \Ufw\Paths::application() . '/config/assets/' . $name . '.php';
                $this->configs[$name] = is_array($loaded) ? $this->normalizeAssetInitData($loaded) : array();
            }
        }
        return $this->configs[$name];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function normalizeAssetInitData($value)
    {
        if (!is_array($value)) {
            return array();
        }
        $out = array();
        foreach ($value as $key => $val) {
            if (is_string($key)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     * @return \Ufw\Container<\Ufw\Utils\Asset\Css>
     */
    protected function getCssSet() {
        if ($this->cssSet === null) {
            $this->cssSet = new \Ufw\Container(\Ufw\Utils\Asset\Css::class);
        }
        return $this->cssSet;
    }
    
    /**
     * @return \Ufw\Container<\Ufw\Utils\Asset\Js>
     */
    protected function getJsSet() {
        if ($this->jsSet === null) {
            $this->jsSet = new \Ufw\Container(\Ufw\Utils\Asset\Js::class);
        }
        return $this->jsSet;
    }
    


}
