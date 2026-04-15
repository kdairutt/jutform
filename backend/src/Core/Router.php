<?php

namespace JutForm\Core;

use ReflectionMethod;

class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable|array, middleware: array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            $params = $this->matchRoute($route['path'], $request->path());
            if ($params === null) {
                continue;
            }
            $handler = $route['handler'];
            $middleware = $route['middleware'];

            $run = function () use ($handler, $request, $params): void {
                $this->invokeHandler($handler, $request, $params);
            };

            $next = $run;
            foreach (array_reverse($middleware) as $mwClass) {
                $next = function () use ($mwClass, $request, $next): void {
                    /** @var MiddlewareInterface $mw */
                    $mw = new $mwClass();
                    $mw->handle($request, $next);
                };
            }
            $next();
            return;
        }
        Response::error('Not found', 404);
    }

    private function invokeHandler(callable|array $handler, Request $request, array $params): void
    {
        if (is_callable($handler) && !is_array($handler)) {
            $handler($request, $params);
            return;
        }
        if (!is_array($handler) || count($handler) !== 2) {
            Response::error('Invalid handler', 500);
        }
        [$class, $method] = $handler;
        $controller = new $class();
        $ref = new ReflectionMethod($class, $method);
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && $type->getName() === Request::class) {
                $args[] = $request;
                continue;
            }
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }
            if ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            $args[] = null;
        }
        $ref->invokeArgs($controller, $args);
    }

    private function matchRoute(string $pattern, string $path): ?array
    {
        $pattern = trim($pattern, '/');
        $path = trim($path, '/');
        if ($pattern === '' && $path === '') {
            return [];
        }
        $patternParts = $pattern === '' ? [] : explode('/', $pattern);
        $pathParts = $path === '' ? [] : explode('/', $path);
        if (count($patternParts) !== count($pathParts)) {
            return null;
        }
        $out = [];
        foreach ($patternParts as $i => $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
                $out[$m[1]] = $pathParts[$i];
            } elseif ($part !== $pathParts[$i]) {
                return null;
            }
        }
        return $out;
    }
}
