<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

class UrlBuilder
{
    public static function urlJoin(string $base, string $link): string
    {
        if ('' === $link) {
            return $base;
        }
        if ('' === $base) {
            return $link;
        }
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $link)) {
            return $link;
        }

        $baseParts = parse_url($base);
        if (false === $baseParts) {
            return $link;
        }

        if ('#' === $link[0]) {
            $baseNoFragment = $base;
            $hashPos = strpos($baseNoFragment, '#');
            if (false !== $hashPos) {
                $baseNoFragment = substr($baseNoFragment, 0, $hashPos);
            }

            return $baseNoFragment.$link;
        }

        if ('?' === $link[0]) {
            $path = $baseParts['path'] ?? '/';

            return static::buildUrl($baseParts, $path.$link);
        }

        if (str_starts_with($link, '//')) {
            $scheme = $baseParts['scheme'] ?? '';

            return ($scheme ? $scheme.':' : '').$link;
        }

        $fragment = '';
        if (false !== ($hashPos = strpos($link, '#'))) {
            $fragment = substr($link, $hashPos);
            $link = substr($link, 0, $hashPos);
        }

        $query = '';
        if (false !== ($queryPos = strpos($link, '?'))) {
            $query = substr($link, $queryPos);
            $link = substr($link, 0, $queryPos);
        }

        if (str_starts_with($link, '/')) {
            $path = static::normalizePath($link);
        } else {
            $basePath = $baseParts['path'] ?? '';
            if ('' === $basePath) {
                $basePath = '/';
            }
            $dir = $basePath;
            if ('/' !== substr($dir, -1)) {
                $lastSlash = strrpos($dir, '/');
                if (false !== $lastSlash) {
                    $dir = substr($dir, 0, $lastSlash + 1);
                } else {
                    $dir = '/';
                }
            }
            $path = static::normalizePath($dir.$link);
        }

        if ('' === $query && isset($baseParts['query']) && '' !== $baseParts['query']) {
            $query = '?'.$baseParts['query'];
        }

        return static::buildUrl($baseParts, $path.$query.$fragment);
    }

    public static function normalizePath(string $path): string
    {
        $leadingSlash = str_starts_with($path, '/');
        $trailingSlash = str_ends_with($path, '/');
        $segments = explode('/', $path);
        $output = [];
        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                array_pop($output);
                continue;
            }
            $output[] = $segment;
        }
        $normalized = implode('/', $output);
        if ($leadingSlash) {
            $normalized = '/'.$normalized;
        }
        if ('' === $normalized) {
            $normalized = $leadingSlash ? '/' : '';
        } elseif ($trailingSlash && '/' !== $normalized) {
            $normalized .= '/';
        }

        return $normalized;
    }

    public static function buildUrl(array $baseParts, string $path): string
    {
        $scheme = $baseParts['scheme'] ?? '';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';
        $user = $baseParts['user'] ?? null;
        $pass = $baseParts['pass'] ?? null;
        $auth = '';
        if (null !== $user) {
            $auth = $user;
            if (null !== $pass) {
                $auth .= ':'.$pass;
            }
            $auth .= '@';
        }
        $authority = $auth.$host.$port;

        return ($scheme ? $scheme.'://' : '').$authority.$path;
    }
}
