<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Minimal pattern router. Routes use {name} placeholders which are passed to
 * the handler as an associative array.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,keys:string[],handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$keys) {
            $keys[] = $m[1];
            // slugs may contain letters, digits and hyphens; ids are digits.
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '/?$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    public function get(string $p, callable $h): void  { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }

    public function dispatch(string $method, string $path): void
    {
        $path = rawurldecode(rtrim($path, '/')) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $m)) {
                array_shift($m);
                $params = array_combine($route['keys'], $m) ?: [];
                ($route['handler'])($params);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', [], 'layout');
    }
}
