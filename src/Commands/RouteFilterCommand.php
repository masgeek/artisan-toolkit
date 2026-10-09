<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

final class RouteFilterCommand extends Command
{
    protected $signature = 'route:filter {pattern : Regex or keyword to filter routes}';

    protected $description = 'Filter routes by pattern';

    public function handle(): int
    {
        $pattern = $this->argument('pattern');
        $routes = Route::getRoutes();
        
        $filtered = [];
        foreach ($routes as $route) {
            if (str_contains($route->uri(), $pattern) || str_contains($route->getName() ?: '', $pattern)) {
                $filtered[] = [$route->uri(), $route->getName() ?: 'N/A', implode(', ', $route->methods())];
            }
        }

        $this->table(['URI', 'Name', 'Methods'], $filtered);

        return self::SUCCESS;
    }
}
