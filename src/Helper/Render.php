<?php
namespace Ufw\Helper;

/**
 * Renders the current action into {@see \Ufw\Response}: HTML via {@see ViewRendererBase::renderHtml} when
 * {@see \Ufw\Response::shouldRenderViewScript()} allows, otherwise merges structured `$vars` for formats such as JSON.
 * Invokable as `$this->render($vars, $script, $controllerDir)`.
 */
class Render extends ViewRendererBase
{
    protected $negotiator = null;

    protected $overwrite = true;

    public function __invoke($vars = array(), $script = null, $controllerDir = true)
    {
        return $this->render($vars, $script, $controllerDir);
    }

    public function render($vars = array(), $script = null, $controllerDir = true)
    {
        if ($this->getResponse()->shouldRenderViewScript()) {
            $html = $this->renderHtml($vars, $script, $controllerDir);
            $this->getResponse()->setContent($this->getOverwrite() ? $html : $this->getResponse()
                ->getContent() . $html);
        } else {
            $this->getResponse()->setContent($this->getOverwrite() ? $vars : array_merge((array) $this->getResponse()
                ->getContent(), $vars));
        }
        return $this;
    }

    public function renderScript($vars = array(), $script = null, $controllerDir = true)
    {
        return $this->renderHtml($vars, $script, $controllerDir);
    }

    /**
     *
     * @return \Negotiation\Negotiator;
     */
    public function getNegotiator()
    {
        if (!isset($this->negotiator)) {
            $this->negotiator = new \Negotiation\Negotiator();
        }
        return $this->negotiator;
    }

    /**
     *
     * @return boolean
     */
    public function getOverwrite()
    {
        return $this->overwrite;
    }

    /**
     *
     * @param boolean $overwrite
     */
    public function setOverwrite($overwrite)
    {
        $this->overwrite = $overwrite;
        return $this;
    }
}