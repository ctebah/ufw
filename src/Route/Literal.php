<?php

namespace Ufw\Route;

/**
 * Exact-path route: no PCRE in config - {@see $routeDef}{@code ['path']} is compared to the request path after
 * normalization (trim slashes; root is {@code /}). Dispatch targets and optional {@see Base::check()} constraints
 * use {@code defaults} like any other route. For URL generation, the path is fixed; extra params go to the query
 * string when {@code withQS} is true (same idea as {@see RegEx} without placeholders).
 */
class Literal extends Base
{

    public function matchRequestPath($path)
    {
        if (!isset($this->routeDef['path']) || !is_string($this->routeDef['path'])) {
            return false;
        }
        $want = self::normalizePath($this->routeDef['path']);
        $got = self::normalizePath($path);
        if ($want !== $got) {
            return false;
        }

        return [
            0 => $path,
        ];
    }

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
        $pathDef = isset($this->routeDef['path']) && is_string($this->routeDef['path']) ? $this->routeDef['path'] : '/';
        $fixedPath = self::normalizePath($pathDef);

        return $this->finalizeBuildUrlFromSegments([$fixedPath], $params, $withQS, $encode);
    }

    /**
     * @return string Normalized path: {@code /} for root, otherwise {@code /segment/...} with no trailing slash except root
     */
    public static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
