<?php
namespace Ufw\Helper;

/**
 * Named CSS/JS bundles from app config (`config/assets/{name}.php`, or `data/assets/` when serving minified
 * bundles). Invokable: `$this->assets('name')` then {@see css()} / {@see js()}.
 */
class Assets extends Base
{

    /**
     *
     * @var \Ufw\Container
     */
    protected $cssSet;
    
    /**
     *
     * @var \Ufw\Container
     */
    protected $jsSet;
    
    protected $name;
    
    protected $configs = [];

    public function __invoke($name)
    {
        $this->setName($name);
        return $this;
    }

    public function css()
    {
        $asset = $this->getCssSet()->get($this->getName());
        if (!$asset->isInitialized()) {
            $asset->init($this->getConfig()['css'])->setApplication($this->getApplication());
        }
        return $asset;
    }
    
    public function js($position = \Ufw\Utils\Asset\Js::POS_BODY_BOTTOM) {
        $asset = $this->getJsSet()->get($this->getName() . '-' . $position);
        if (!$asset->isInitialized()) {
            $asset->init($this->getConfig()['js'])->setPosition($position)->setApplication($this->getApplication());
        }
        return $asset;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    
    protected function getConfig() {
        if (!isset($this->configs[$this->name])) {
            $min = $this->getApplication()->getConfig()['assets']['serve-minified'];
            $this->configs[$this->name] = [];
            if ($min) {
                $this->configs[$this->name] = include \Ufw\Paths::application() . '/data/assets/' . $this->getName() . '.php';
            }
            if (!$this->configs[$this->name]) {
                $this->configs[$this->name] = include \Ufw\Paths::application() . '/config/assets/' . $this->getName() . '.php';
            }
        }
        return $this->configs[$this->name];
    }
   
    /**
     * @return \Ufw\Container
     */
    protected function getCssSet() {
        if (!isset($this->cssSet)) {
            $this->cssSet = new \Ufw\Container(\Ufw\Utils\Asset\Css::class);
        }
        return $this->cssSet;
    }
    
    /**
     * @return \Ufw\Container
     */
    protected function getJsSet() {
        if (!isset($this->jsSet)) {
            $this->jsSet = new \Ufw\Container(\Ufw\Utils\Asset\Js::class);
        }
        return $this->jsSet;
    }
    


}