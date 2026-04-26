<?php

namespace Ufw\Route;

/**
 * Regex-based route: {@see buildUrl()} picks the best matching `urlBuilder` pattern, substitutes placeholders, and
 * optionally appends key/value URI segments or a query string.
 */
class RegEx extends Base
{

    public function buildUrl($params = array(), $reset = true, $withQS = false, $encode = true)
    {
        $params = $this->prepareBuildUrlParams($params, $reset);

        $urlPart = $pattern = $this->getUrlBuilderString($params);
        foreach ($this->routeDef['urlBuilder']['compiled'][$pattern] as $varName) {
            $urlPart = preg_replace("~:$varName\b~", $encode ? urlencode($params[$varName]) : $params[$varName], $urlPart);
            unset($params[$varName]);
        }

        return $this->finalizeBuildUrlFromSegments([$urlPart], $params, $withQS, $encode);
    }

    protected function getUrlBuilderString($params)
    {
        if (!@$this->routeDef['urlBuilder']['compiled']) {
            foreach ($this->getRouteDef()['urlBuilder'] as $pattern) {
                $matches = [];
                preg_match_all('~:([a-z0-9\_]+)\b~', $pattern, $matches, PREG_PATTERN_ORDER);
                $this->routeDef['urlBuilder']['compiled'][$pattern] = $matches[1];
            }
        }

        $paramKeys = array_keys($params);
        $bestMatch = [
            'pattern' => null,
            'count' => -1
        ];
        foreach ($this->routeDef['urlBuilder']['compiled'] as $pattern => $patternPlaceholders) {
            if (!array_diff($patternPlaceholders, $paramKeys)) {
                $no = count(array_intersect($patternPlaceholders, $paramKeys));
                if ($bestMatch['count'] < $no) {
                    $bestMatch = [
                        'pattern' => $pattern,
                        'count' => $no
                    ];
                }
            }
        }

        return $bestMatch['pattern'];
    }
}
