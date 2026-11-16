<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

final class RouteFilterCommand extends Command
{
    protected $signature = 'route:filter 
                            {pattern : Regex or keyword to filter routes} 
                            {--method= : Only routes handling this HTTP method} 
                            {--middleware= : Only routes whose middleware contains this value} 
                            {--except= : Comma-separated keywords to exclude} 
                            {--json : Emit results as JSON instead of a table}';

    protected $description = 'Filter routes by pattern';

    public function handle(): int
    {
        $pattern = (string) $this->argument('pattern');
        $method = strtoupper((string) $this->option('method'));
        $middleware = strtolower((string) $this->option('middleware'));
        $except = array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', (string) $this->option('except')),
        ));

        $filtered = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $name = $route->getName() ?: 'N/A';
            $methods = $route->methods();
            $routeMiddleware = array_values(array_filter(
                $route->gatherMiddleware(),
                static fn (mixed $value): bool => is_string($value),
            ));

            if (! $this->matchesPattern($pattern, $uri, $name)) {
                continue;
            }

            if ($method !== '' && ! in_array($method, array_map('strtoupper', $methods), true)) {
                continue;
            }

            if ($middleware !== '' && ! $this->middlewareMatches($routeMiddleware, $middleware)) {
                continue;
            }

            if ($except !== [] && $this->excluded($except, $uri, $name)) {
                continue;
            }

            $filtered[] = [
                'uri' => $uri,
                'name' => $name,
                'methods' => $methods,
                'middleware' => $routeMiddleware,
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['URI', 'Name', 'Methods', 'Middleware'],
            array_map(static fn (array $route): array => [
                $route['uri'],
                $route['name'],
                implode(', ', $route['methods']),
                implode(', ', $route['middleware']),
            ], $filtered),
        );

        return self::SUCCESS;
    }

    private function matchesPattern(string $pattern, string $uri, string $name): bool
    {
        if ($pattern === '') {
            return true;
        }

        if (@preg_match($pattern, '') !== false) {
            return preg_match($pattern, $uri) === 1 || preg_match($pattern, $name) === 1;
        }

        return str_contains($uri, $pattern) || str_contains($name, $pattern);
    }

    /**
     * @param  list<string>  $routeMiddleware
     */
    private function middlewareMatches(array $routeMiddleware, string $needle): bool
    {
        foreach ($routeMiddleware as $value) {
            if (str_contains(strtolower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $except
     */
    private function excluded(array $except, string $uri, string $name): bool
    {
        foreach ($except as $keyword) {
            if (str_contains(strtolower($uri), $keyword) || str_contains(strtolower($name), $keyword)) {
                return true;
            }
        }

        return false;
    }
}
