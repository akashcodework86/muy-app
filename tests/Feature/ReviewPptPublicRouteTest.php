<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

class ReviewPptPublicRouteTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function test_review_ppt_routes_are_public_and_unlisted_by_auth_middleware(): void
    {
        $expected = [
            ['admin.review-ppt.index', 'admin/review-ppt-generator'],
            ['admin.review-ppt.preview', 'admin/review-ppt-generator/preview'],
            ['admin.review-ppt.download', 'admin/review-ppt-generator/download'],
        ];

        foreach ($expected as [$name, $uri]) {
            /** @var Route|null $route */
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertSame($uri, $route->uri());
            $this->assertSame(['GET', 'HEAD'], $route->methods());

            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware);
            $this->assertNotContains('active', $middleware);
            $this->assertNotContains('state_admin', $middleware);
            $this->assertTrue(collect($middleware)->contains(
                fn (string $item): bool => str_starts_with($item, 'throttle:'),
            ));
        }
    }
}
