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
    /**
     * @var array<string, array<int|string, mixed>>
     */
    protected $trees = array();

    /**
     * @var string|array<int|string, mixed>|null
     */
    protected $definition = null;

    /**
     * @var bool
     */
    protected $strictMode = false;

    /**
     * @var string|null
     */
    protected static $renderHelperName = null;

    /**
     * @param string|array<int|string, mixed> $def
     * @return $this
     */
    public function __invoke($def)
    {
        $this->setDefinition($def);
        return $this;
    }

    /**
     * @param string|null $script
     * @param list<array<string, mixed>> $prependNodes
     * @param bool $buildFromPath
     * @return string
     */
    public function breadcrumbs($script = null, $prependNodes = array(), $buildFromPath = true)
    {
        $path = array();
        if ($buildFromPath) {
            $this->buildPath($this->getConfig(), $path);
        }
        return $this->partial($script ?? $this->getRenderHelperName() . '/breadcrumbs.php', [
            'items' => array_merge($prependNodes, $path)
        ]);
    }

    /**
     * @param string|null $script
     * @return string
     */
    public function render($script = null)
    {
        return $this->partial($script ?? 'menu-generic.php', [
            'items' => $this->getConfig()
        ]);
    }

    /**
     * @param array<int|string, mixed> $config
     * @param list<array<int|string, mixed>> $out
     * @return void
     */
    protected function buildPath($config, &$out)
    {
        foreach ($config as $key => $item) {
            if (!is_numeric($key) || !is_array($item)) {
                continue;
            }
            if (!empty($item['active'])) {
                if ($this->hasOwnContent($item, false)) {
                    $out[] = $item;
                }
                if (!empty($item['items']) && is_array($item['items'])) {
                    $this->buildPath($item['items'], $out);
                }
            }
        }
    }

    /**
     * @param array<int|string, mixed> $config
     * @param string $currentUrl
     * @param bool $strict
     * @return array<int|string, mixed>
     */
    protected function buildTree($config, $currentUrl, $strict = false)
    {
        $out = array();

        $active = false;
        $wrapper = null;
        foreach ($config as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
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
                    $roles = array();
                    foreach ($item['rbac'] as $role) {
                        if (is_string($role)) {
                            $roles[] = $role;
                        }
                    }
                    if (!$this->user->hasAnyOfRoles($roles)) {
                        continue;
                    }
                }

                unset($item['rbac']);
            }

            if (isset($item['env'])) {
                $envList = is_array($item['env']) ? $item['env'] : array();
                $allowed = array();
                foreach ($envList as $envName) {
                    if (is_string($envName)) {
                        $allowed[] = $envName;
                    }
                }
                if (!in_array(\Ufw\Envs::runtimeLabel(), $allowed, true)) {
                    continue;
                }
                unset($item['env']);
            }

            if (is_numeric($key)) {
                if (isset($item['caption']) && is_callable($item['caption'])) {
                    $item['caption'] = $item['caption']();
                }

                if (!isset($item['content']) || !is_array($item['content'])) {
                    $item['content'] = array();
                }
                if (isset($item['content']['href']) && is_array($item['content']['href'])) {
                    $hrefParams = $item['content']['href'];
                    $route = \Ufw\array_get_and_unset($hrefParams, 'route');
                    $routeName = is_string($route) ? $route : 'default';
                    $item['content']['href'] = $this->url($hrefParams, true, false, $routeName);
                }

                $href = isset($item['content']['href']) && is_string($item['content']['href']) ? $item['content']['href'] : null;
                if ($href !== null) {
                    if ($this->getStrictMode()) {
                        if ($this->isMenuItemActiveStrict($href, $currentUrl)) {
                            $item['active'] = true;
                            $active = true;
                        }
                    } elseif ($this->isMenuItemActive($href, $currentUrl)) {
                        $item['active'] = true;
                        $active = true;
                    }
                }

                if (isset($item['items']) && is_array($item['items'])) {
                    $item['items'] = $this->buildTree($item['items'], $currentUrl, $strict);
                    if ($this->empty($item['items'])) {
                        unset($item['items']);
                    } else {
                        $childActive = !empty($item['items']['active']);
                        $item['active'] = !empty($item['active']) || $childActive;
                        $active = $active || !empty($item['active']);
                    }
                }

                if (!$this->empty($item)) {
                    $itemActive = !empty($item['active']);
                    if (isset($item['multiwrap'])) {
                        $item['wrapper'] = $this->formatWrap($this->normalizeWrapList($item['multiwrap']), 'li', null, $itemActive);
                    } elseif (isset($item['wrap'])) {
                        $item['wrapper'] = $this->formatWrap($this->normalizeWrapList(array($item['wrap'])), 'li', 'nav-item', $itemActive);
                    } else {
                        $item['wrapper'] = $this->formatWrap(array(array()), 'li', 'nav-item', $itemActive);
                    }
                    unset($item['wrap'], $item['multiwrap']);

                    $out[$key] = $item;
                }
            } elseif (in_array($key, array(
                'wrap',
                'multiwrap',
            ), true)) {
                $wrapper = $key == 'wrap' ? array($item) : $item;
            }
        }

        if (!$this->empty($out)) {
            $out['active'] = $active;
            $wrapSource = is_array($wrapper) ? $this->normalizeWrapList($wrapper) : array(array());
            $out['wrapper'] = $this->formatWrap($wrapSource, 'ul', 'nav navbar-nav navbar-left', $active);
        }

        return $out;
    }

    /**
     * @param string $menuItemUrl
     * @param string $currentUrl
     * @return bool
     */
    protected function isMenuItemActive($menuItemUrl, $currentUrl)
    {
        return !strncmp($menuItemUrl, $currentUrl, strlen($menuItemUrl));
    }

    /**
     * @param string $menuItemUrl
     * @param string $currentUrl
     * @return bool
     */
    protected function isMenuItemActiveStrict($menuItemUrl, $currentUrl)
    {
        return !@strcmp($menuItemUrl, $currentUrl);
    }

    /**
     * @param list<array<string, mixed>> $wrapData
     * @param string $tag
     * @param string|null $class
     * @param bool $active
     * @return list<array<string, mixed>>
     */
    protected function formatWrap($wrapData, $tag = 'li', $class = null, $active = false)
    {
        $out = array();
        foreach ($wrapData as $value) {
            $wrapper = array(
                'tag' => isset($value['tag']) ? $value['tag'] : $tag,
                'attribs' => array(
                    'class' => $class
                )
            );
            unset($value['tag']);
            $wrapper['attribs'] = array_merge($wrapper['attribs'], $value);
            if ($active) {
                $className = isset($wrapper['attribs']['class']) && is_string($wrapper['attribs']['class']) ? $wrapper['attribs']['class'] : '';
                $wrapper['attribs']['class'] = $className . ' active';
            }
            $out[] = $wrapper;
        }
        return $out;
    }

    /**
     * @param array<int|string, mixed> $subMenu
     * @return bool
     */
    protected function empty($subMenu)
    {
        return !$this->hasChildren($subMenu) && !$this->hasOwnContent($subMenu, true);
    }

    /**
     * @param array<int|string, mixed> $subMenu
     * @return bool
     */
    protected function hasChildren($subMenu)
    {
        return (bool) array_filter(array_keys($subMenu), function ($key) {
            return is_numeric($key) || in_array($key, [
                'items'
            ]);
        });
    }

    /**
     * @param array<int|string, mixed> $subMenu
     * @param bool $withContainerOption
     * @return bool
     */
    protected function hasOwnContent($subMenu, $withContainerOption = true)
    {
        $ownContent = (isset($subMenu['content']) && $subMenu['content']) || (isset($subMenu['caption']) && $subMenu['caption']);
        $containerOnly = $withContainerOption ? (isset($subMenu['container-only']) ? $subMenu['container-only'] : false) : false;
        return $ownContent && !$containerOnly;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function getConfig()
    {
        $ident = md5($this->getStrictMode() . '|' . json_encode($this->definition));
        if (!isset($this->trees[$ident])) {
            if (is_string($this->definition)) {
                $loaded = include $this->definition;
                $config = is_array($loaded) ? $loaded : array();
            } elseif (is_array($this->definition)) {
                $config = $this->definition;
            } else {
                $config = array();
            }
            $this->trees[$ident] = $this->buildTree($config, $this->url(array(), false, false, null));
        }

        return $this->trees[$ident];
    }

    /**
     * @return string|array<int|string, mixed>|null
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * @param string|array<int|string, mixed>|null $definition
     * @return void
     */
    public function setDefinition($definition)
    {
        $this->definition = $definition;
    }

    /**
     * @return bool
     */
    public function getStrictMode()
    {
        return $this->strictMode;
    }

    /**
     * @param bool $strictMode
     * @return $this
     */
    public function setStrictMode($strictMode)
    {
        $this->strictMode = $strictMode;
        return $this;
    }

    /**
     * @return string
     */
    public function getRenderHelperName()
    {
        if (!static::$renderHelperName) {
            $app = \Ufw\Registry::getInstance()->get('application');
            $helperName = 'bootstrap3';
            if ($app instanceof \Ufw\Application) {
                $appConfig = $app->getConfig();
                if (is_array($appConfig) && isset($appConfig['form']) && is_array($appConfig['form'])) {
                    $formConfig = $appConfig['form'];
                    if (isset($formConfig['helper']) && is_string($formConfig['helper']) && $formConfig['helper'] !== '') {
                        $helperName = $formConfig['helper'];
                    }
                }
            }
            self::setRenderHelperName($helperName);
        }
        return (string) static::$renderHelperName;
    }

    /**
     * @param string $renderHelperName
     * @return void
     */
    public static function setRenderHelperName($renderHelperName)
    {
        static::$renderHelperName = $renderHelperName;
    }

    /**
     * @param mixed $wrapData
     * @return list<array<string, mixed>>
     */
    protected function normalizeWrapList($wrapData)
    {
        if (!is_array($wrapData)) {
            return array(array());
        }
        $out = array();
        foreach ($wrapData as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = array();
            foreach ($entry as $entryKey => $entryVal) {
                if (is_string($entryKey)) {
                    $normalized[$entryKey] = $entryVal;
                }
            }
            $out[] = $normalized;
        }
        if ($out === array()) {
            return array(array());
        }

        return $out;
    }
}
