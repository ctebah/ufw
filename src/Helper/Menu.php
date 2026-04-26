<?php

namespace Ufw\Helper;

/**
 * Builds a navigation tree from a config array or include path: marks active items from the current URL, optional
 * RBAC / environment gates, then outputs HTML via {@see Partial} (default `menu-generic.php`, breadcrumbs from
 * {@see getRenderHelperName()} partials).
 *
 * Menu items may include an **`env`** key (list of allowed environment names). The current label is
 * {@see \Ufw\Envs::runtimeLabel()}.
 */
class Menu extends Base
{
    protected $trees = [];

    protected $definition = null;

    protected $strictMode = false;

    protected static $renderHelperName = null;

    public function __invoke($def)
    {
        $this->setDefinition($def);
        return $this;
    }

    public function breadcrumbs($script = null, $prependNodes = [], $buildFromPath = true)
    {
        $path = [];
        if ($buildFromPath) {
            $this->buildPath($this->getConfig(), $path);
        }
        return $this->partial($script ?? $this->getRenderHelperName() . '/breadcrumbs.php', [
            'items' => array_merge($prependNodes, $path)
        ]);
    }

    public function render($script = null)
    {
        return $this->partial($script ?? 'menu-generic.php', [
            'items' => $this->getConfig()
        ]);
    }

    protected function buildPath($config, &$out)
    {
        foreach ($config as $key => $item) {
            if (is_numeric($key)) {
                if ($item['active']) {
                    if ($this->hasOwnContent($item, false)) {
                        $out[] = $item;
                    }
                    if ($item['items']) {
                        $this->buildPath($item['items'], $out);
                    }
                }
            }
        }
    }

    protected function buildTree(&$config, $currentUrl, $strict = false)
    {
        $out = [];

        $active = false;
        foreach ($config as $key => $item) {
            if (isset($item['rbac'])) {
                if (is_callable($item['rbac'])) {
                    if (!$item['rbac']()) {
                        continue;
                    }
                } elseif (is_string($item['rbac'])) {
                    if (!$this->user->hasRole($item['rbac'])) {
                        continue;
                    }
                } elseif (is_array($item['rbac'])) {
                    if (!$this->user->hasAnyOfRoles($item['rbac'])) {
                        continue;
                    }
                }

                unset($item['rbac']);
            }

            if (isset($item['env'])) {
                if (!in_array(\Ufw\Envs::runtimeLabel(), $item['env'])) {
                    continue;
                }
                unset($item['env']);
            }

            if (is_numeric($key)) {
                if (isset($item['caption']) && is_callable($item['caption'])) {
                    $item['caption'] = $item['caption']();
                }

                if (@is_array($item['content']['href'])) {
                    if ($route = \Ufw\array_get_and_unset($item['content']['href'], 'route')) {
                        $item['content']['href'] = $this->url($item['content']['href'], true, false, $route);
                    } else {
                        $item['content']['href'] = $this->url($item['content']['href']);
                    }
                }

                if ($this->getStrictMode()) {
                    if (isset($item['content']['href']) && $this->isMenuItemActiveStrict($item['content']['href'], $currentUrl)) {
                        $item['active'] = true;
                        $active = true;
                    }
                } else {
                    if (isset($item['content']['href']) && $this->isMenuItemActive($item['content']['href'], $currentUrl)) {
                        $item['active'] = true;
                        $active = true;
                    }
                }

                if (isset($item['items'])) {
                    $item['items'] = $this->buildTree($item['items'], $currentUrl, $strict);
                    if ($this->empty($item['items'])) {
                        unset($item['items']);
                    } else {
                        $item['active'] = @$item['active'] || @$item['items']['active'];
                        $active = $active || $item['active'];
                    }
                }

                if (!$this->empty($item)) {
                    if (isset($item['multiwrap'])) {
                        $item['wrapper'] = $this->formatWrap($item['multiwrap'], 'li', null, $item['active']);
                    } elseif (isset($item['wrap'])) {
                        $item['wrapper'] = $this->formatWrap([
                            $item['wrap']
                        ], 'li', 'nav-item', @$item['active']);
                    } else {
                        $item['wrapper'] = $this->formatWrap([
                            []
                        ], 'li', 'nav-item', @$item['active']);
                    }
                    unset($item['wrap'], $item['multiwrap']);

                    $out[$key] = $item;
                }
            } elseif (in_array($key, [
                'wrap',
                'multiwrap'
            ])) {
                $wrapper = $key == 'wrap' ? [
                    $item
                ] : $item;
            }
        }

        if (!$this->empty($out)) {
            $out['active'] = $active;
            $out['wrapper'] = $this->formatWrap($wrapper, 'ul', 'nav navbar-nav navbar-left', $out['active']);
        }

        return $out;
    }

    protected function isMenuItemActive($menuItemUrl, $currentUrl)
    {
        return !strncmp($menuItemUrl, $currentUrl, strlen($menuItemUrl));
    }

    protected function isMenuItemActiveStrict($menuItemUrl, $currentUrl)
    {
        return !@strcmp($menuItemUrl, $currentUrl);
    }

    protected function formatWrap($wrapData, $tag = 'li', $class = null, $active = false)
    {
        $out = [];
        foreach ($wrapData as $value) {
            $wrapper = [
                'tag' => isset($value['tag']) ? $value['tag'] : $tag,
                'attribs' => [
                    'class' => $class
                ]
            ];
            unset($value['tag']);
            $wrapper['attribs'] = array_merge($wrapper['attribs'], $value);
            // if (($wrapper['tag'] == 'li') && $active) {
            if ($active) {
                $wrapper['attribs']['class'] .= ' active';
            }
            $out[] = $wrapper;
        }
        return $out;
    }

    protected function empty(&$subMenu)
    {
        return !$this->hasChildren($subMenu) && !$this->hasOwnContent($subMenu, true);
    }

    protected function hasChildren(&$subMenu)
    {
        return array_filter(array_keys($subMenu), function ($key) {
            return is_numeric($key) || in_array($key, [
                'items'
            ]);
        });
    }

    protected function hasOwnContent(&$subMenu, $withContainerOption = true)
    {
        $ownContent = (isset($subMenu['content']) && $subMenu['content']) || (isset($subMenu['caption']) && $subMenu['caption']);
        $containerOnly = $withContainerOption ? (isset($subMenu['container-only']) ? $subMenu['container-only'] : false) : false;
        return $ownContent && !$containerOnly;
    }

    protected function getConfig()
    {
        $ident = md5($this->getStrictMode() . '|' . json_encode($this->definition));
        if (!isset($this->trees[$this->definition])) {
            if (is_string($this->definition)) {
                $config = include $this->definition;
            } else {
                $config = $this->definition;
            }
            $this->trees[$ident] = $this->buildTree($config, $this->url([], false, false, null));
        }

        return $this->trees[$ident];
    }

    public function getDefinition()
    {
        return $this->definition;
    }

    public function setDefinition($definition)
    {
        $this->definition = $definition;
    }

    /**
     *
     * @return $strictMode
     */
    public function getStrictMode()
    {
        return $this->strictMode;
    }

    /**
     *
     * @param boolean $strictMode
     */
    public function setStrictMode($strictMode)
    {
        $this->strictMode = $strictMode;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getRenderHelperName()
    {
        if (!static::$renderHelperName) {
            $helperName = @\Ufw\Registry::getInstance()->get('application')->getConfig()['form']['helper'];
            if (!$helperName) {
                $helperName = 'bootstrap3'; // default; use bootstrap4 or bootstrap5 to match form.helper / Bootstrap* partials
            }
            self::setRenderHelperName($helperName);
        }
        return static::$renderHelperName;
    }

    /**
     *
     * @param string $renderHelperName
     */
    public static function setRenderHelperName($renderHelperName)
    {
        static::$renderHelperName = $renderHelperName;
    }
}
