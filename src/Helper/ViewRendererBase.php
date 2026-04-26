<?php

namespace Ufw\Helper;

/**
 * Helpers that include PHP view scripts from the application tree with a fallback to framework `src/views/`.
 */
abstract class ViewRendererBase extends Base
{
    protected static $fwViewsPath;

    /**
     * Absolute path to bundled framework views (`src/views`).
     *
     * @return string
     */
    protected function getFwViewsPath()
    {
        if (!isset(self::$fwViewsPath)) {
            self::$fwViewsPath = realpath(__DIR__ . '/../views');
        }
        return self::$fwViewsPath;
    }

    /**
     * Includes a view script: application `views/` first, then framework `src/views/`.
     *
     * @param array $vars Variables passed into the script via {@see extract()}
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

        return ob_get_clean();
    }
}
