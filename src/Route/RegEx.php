<?php

namespace Ufw\Route;

/**
 * Regex-based route: {@see buildUrl()} picks the best matching `urlBuilder` pattern, substitutes placeholders, and
 * optionally appends key/value URI segments or a query string.
 */
class RegEx extends Base
{

    /**
     * @param array<string, mixed> $params
     * @param bool $reset
     * @param bool $withQS
     * @param bool $encode
     * @return string
     */
    public function buildUrl($params = array(), $reset = true, $withQS = false, $encode = true)
    {
        $params = $this->prepareBuildUrlParams($params, $reset);

        $pattern = $this->getUrlBuilderString($params);
        $urlPart = is_string($pattern) ? $pattern : '';
        $names = array();
        $builder = (isset($this->routeDef['urlBuilder']) && is_array($this->routeDef['urlBuilder'])) ? $this->routeDef['urlBuilder'] : array();
        if (is_string($pattern) && isset($builder['compiled']) && is_array($builder['compiled']) && isset($builder['compiled'][$pattern]) && is_array($builder['compiled'][$pattern])) {
            $names = $builder['compiled'][$pattern];
        }
        foreach ($names as $varName) {
            if (!is_string($varName)) {
                continue;
            }
            $raw = isset($params[$varName]) ? $params[$varName] : '';
            $replacement = $encode ? urlencode($this->optionalRouteString($raw) ?: '') : ($this->optionalRouteString($raw) ?: '');
            $next = preg_replace('~:' . preg_quote($varName, '~') . '\b~', $replacement, $urlPart);
            $urlPart = is_string($next) ? $next : $urlPart;
            unset($params[$varName]);
        }

        return $this->finalizeBuildUrlFromSegments(array($urlPart), $params, $withQS, $encode);
    }

    /**
     * @param array<string, mixed> $params
     * @return string|null
     */
    protected function getUrlBuilderString($params)
    {
        $builder = (isset($this->routeDef['urlBuilder']) && is_array($this->routeDef['urlBuilder'])) ? $this->routeDef['urlBuilder'] : array();
        if (!isset($builder['compiled']) || !is_array($builder['compiled'])) {
            if (!isset($this->routeDef['urlBuilder']) || !is_array($this->routeDef['urlBuilder'])) {
                $this->routeDef['urlBuilder'] = array();
            }
            $this->routeDef['urlBuilder']['compiled'] = array();
            foreach ($builder as $patternKey => $pattern) {
                if ($patternKey === 'compiled' || !is_string($pattern)) {
                    continue;
                }
                $matches = array();
                preg_match_all('~:([a-z0-9\_]+)\b~', $pattern, $matches, PREG_PATTERN_ORDER);
                $this->routeDef['urlBuilder']['compiled'][$pattern] = $matches[1];
            }
            $builder = $this->routeDef['urlBuilder'];
        }

        $compiled = $builder['compiled'];
        $paramKeys = array_keys($params);
        $bestMatch = array(
            'pattern' => null,
            'count' => -1,
        );
        foreach ($compiled as $pattern => $patternPlaceholders) {
            if (!is_string($pattern) || !is_array($patternPlaceholders)) {
                continue;
            }
            $placeholderList = array();
            foreach ($patternPlaceholders as $placeholder) {
                if (is_string($placeholder)) {
                    $placeholderList[] = $placeholder;
                }
            }
            if (!array_diff($placeholderList, $paramKeys)) {
                $no = count(array_intersect($placeholderList, $paramKeys));
                if ($bestMatch['count'] < $no) {
                    $bestMatch = array(
                        'pattern' => $pattern,
                        'count' => $no,
                    );
                }
            }
        }

        $selected = $bestMatch['pattern'];

        return is_string($selected) ? $selected : null;
    }
}
