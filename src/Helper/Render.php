<?php
namespace Ufw\Helper;

/**
 * Renders the current action into {@see \Ufw\Response}: HTML via {@see ViewRendererBase::renderHtml} when
 * {@see \Ufw\Response::shouldRenderViewScript()} allows, otherwise merges structured `$vars` for formats such as JSON.
 * Invokable as `$this->render($vars, $script, $controllerDir)`.
 */
class Render extends ViewRendererBase
{
    /**
     * @var \Negotiation\Negotiator|null
     */
    protected $negotiator = null;

    /**
     * @var bool
     */
    protected $overwrite = true;

    /**
     * @param array<string, mixed> $vars
     * @param string|null $script
     * @param bool $controllerDir
     * @return $this
     */
    public function __invoke($vars = array(), $script = null, $controllerDir = true)
    {
        return $this->render($vars, $script, $controllerDir);
    }

    /**
     * @param array<string, mixed> $vars
     * @param string|null $script
     * @param bool $controllerDir
     * @return $this
     */
    public function render($vars = array(), $script = null, $controllerDir = true)
    {
        if ($this->getResponse()->shouldRenderViewScript()) {
            $html = $this->renderHtml($vars, $script, $controllerDir);
            $existing = $this->getResponse()->getContent();
            $existingHtml = is_string($existing) ? $existing : '';
            $this->getResponse()->setContent($this->getOverwrite() ? $html : $existingHtml . $html);
        } else {
            $this->getResponse()->setContent($this->getOverwrite() ? $vars : array_merge((array) $this->getResponse()
                ->getContent(), $vars));
        }
        return $this;
    }

    /**
     * @param array<string, mixed> $vars
     * @param string|null $script
     * @param bool $controllerDir
     * @return string
     */
    public function renderScript($vars = array(), $script = null, $controllerDir = true)
    {
        return $this->renderHtml($vars, $script, $controllerDir);
    }

    /**
     * @return \Negotiation\Negotiator
     */
    public function getNegotiator()
    {
        if (!isset($this->negotiator)) {
            $this->negotiator = new \Negotiation\Negotiator();
        }
        return $this->negotiator;
    }

    /**
     * @return bool
     */
    public function getOverwrite()
    {
        return $this->overwrite;
    }

    /**
     * @param bool $overwrite
     * @return $this
     */
    public function setOverwrite($overwrite)
    {
        $this->overwrite = $overwrite;
        return $this;
    }
}
