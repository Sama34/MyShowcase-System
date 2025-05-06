<?php

declare(strict_types=1);

namespace MyShowcase\System;

use const MyShowcase\ROOT;

class Router
{
    public function __construct(
        protected array $routes = [],
        public array $params = [],
    ) {
        return $this;
    }

    public function get(array|string $paths, string $controller, string $method): void
    {
        if (!is_array($paths)) {
            $paths = [$paths];
        }

        foreach ($paths as $path) {
            $this->addRoute('get', $path, $controller, $method);
        }
    }

    public function post($path, $controller, $method): void
    {
        $this->addRoute('post', $path, $controller, $method);
    }

    protected function addRoute($httpMethod, $path, $controller, $method): void
    {
        $result = $this->convertPathToRegex($path);

        $this->routes[$path] = [
            'method' => $httpMethod,
            'regex' => '#^' . preg_replace('/\{(\w+)\}/', '([^/]+)', $path) . '$#',
            'controller' => $controller,
            'action' => $method,
            'params' => $result['params']
        ];
    }

    protected function convertPathToRegex($path): array
    {
        $paramNames = [];

        $regex = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([a-zA-Z0-9_\-]+)';
        }, $path);

        return [
            'regex' => '/^' . str_replace('/', '\/', $regex) . '$/',
            'params' => $paramNames
        ];
    }

    public function run(): void
    {
        global $mybb;

        $routePath = $this->getRoutePath();

        foreach ($this->routes as $pattern => $route) {
            if ($route['method'] !== $mybb->request_method) {
                continue;
            }

            if (preg_match($route['regex'], $routePath, $matches)) {
                $this->params = $this->extractParams($route, $matches);

                $this->callController($route['controller'], $route['action'], $this->params);

                return;
            }
        }

        http_response_code(404);

        echo '404 Not Found';
    }

    protected function getRoutePath(): string
    {
        global $mybb;

        return $mybb->input['route'] ?? '/';
    }

    protected function extractParams($route, $matches): array
    {
        $params = [];

        foreach ($route['params'] as $index => $name) {
            $params[$name] = $matches[$index + 1];
        }

        return $params;
    }

    protected function callController($controllerClass, $method, $params): void
    {
        if (file_exists(ROOT . "/Controllers/{$controllerClass}.php")) {
            require_once ROOT . "/Controllers/{$controllerClass}.php";
        }

        $controllerClass = '\\MyShowcase\\Controllers\\' . $controllerClass;

        $controller = new $controllerClass($this);

        call_user_func_array([$controller, $method], array_values($params));
    }
}