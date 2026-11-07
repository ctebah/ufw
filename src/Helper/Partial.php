<?php
namespace Ufw\Helper;

/**
 * Renders a reusable fragment from `views/partials/{script}` (application overrides framework views).
 * Invokable: `$this->partial('foo/bar', $vars)` from controllers/helpers.
 */
class Partial extends ViewRendererBase
{

    /**
     * @param string $script
     * @param array<string, mixed> $vars
     * @return string
     */
    public function __invoke($script, $vars = array())
    {
        return $this->renderHtml($vars, "partials/$script", false);
    }
}
