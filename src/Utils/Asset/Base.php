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

    protected $inited = false;

    /**
     *
     * @var array
     */
    protected $data = null;

    /**
     *
     * @var string
     */
    protected $compiled = null;

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

    public function isInitialized()
    {
        return $this->getInited();
    }

    /**
     *
     * @return string
     */
    abstract protected function getCompiled();

    /**
     *
     * @return boolean
     */
    protected function getInited()
    {
        return $this->inited;
    }

    /**
     *
     * @param boolean $inited
     */
    protected function setInited($inited)
    {
        $this->inited = $inited;
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     *
     * @param array $data
     */
    protected function setData($data = null)
    {
        $this->data = $data;
        $this->compiled = null;
        return $this;
    }

    /**
     *
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return $this->application;
    }
    
    /**
     *
     * @param \Ufw\Application $application
     */
    public function setApplication($application)
    {
        $this->application = $application;
        return $this;
    }
}