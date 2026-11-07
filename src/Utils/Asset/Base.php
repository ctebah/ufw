<?php
namespace Ufw\Utils\Asset;

/**
 * Lazy HTML fragment from config `data` (CDN/local lists): {@see init()}, string cast returns {@see getCompiled()}.
 * Concrete subclasses emit `<script>` or `<link>` tags for asset manifests.
 */
abstract class Base
{
    /**
     * @var \Ufw\Application
     */
    protected $application;

    /**
     * @var bool
     */
    protected $inited = false;

    /**
     * @var array<string, mixed>|null
     */
    protected $data = null;

    /**
     * @var string|null
     */
    protected $compiled = null;

    /**
     * @param array<string, mixed> $data
     * @return $this
     */
    public function init($data)
    {
        $this->setData($data)->setInited(true);
        return $this;
    }

    public function __toString()
    {
        if (!$this->getData()) {
            return '';
        }
        return $this->getCompiled();
    }

    /**
     * @return bool
     */
    public function isInitialized()
    {
        return $this->getInited();
    }

    /**
     * @return string
     */
    abstract protected function getCompiled();

    /**
     * @return bool
     */
    protected function getInited()
    {
        return $this->inited;
    }

    /**
     * @param bool $inited
     * @return $this
     */
    protected function setInited($inited)
    {
        $this->inited = $inited;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed>|null $data
     * @return $this
     */
    protected function setData($data = null)
    {
        $this->data = $data;
        $this->compiled = null;
        return $this;
    }

    /**
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return $this->application;
    }
    
    /**
     * @param \Ufw\Application $application
     * @return $this
     */
    public function setApplication($application)
    {
        $this->application = $application;
        return $this;
    }

    /**
     * @return \Ufw\Helper\Html5Tag
     */
    protected function getHtml5TagHelper()
    {
        $helper = $this->getApplication()->getHelper('html5Tag');
        if (!$helper instanceof \Ufw\Helper\Html5Tag) {
            throw new \RuntimeException('html5Tag helper is not available');
        }
        return $helper;
    }
}
