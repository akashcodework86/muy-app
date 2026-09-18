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

    public function test_review_ppt_routes_require_auth_and_review_ppt_access(): void
    {
        $expected = [
            ['admin.review-ppt.index', 'admin/review-ppt-generator'],
            ['admin.review-ppt.preview', 'admin/review-ppt-generator/preview'],
            ['admin.review-ppt.download', 'admin/review-ppt-generator/download'],
            ['hub.review-ppt.index', 'hub/review-ppt-generator'],
            ['hub.review-ppt.preview', 'hub/review-ppt-generator/preview'],
            ['hub.review-ppt.download', 'hub/review-ppt-generator/download'],
        ];

        foreach ($expected as [$name, $uri]) {
            /** @var Route|null $route */
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertSame($uri, $route->uri());
            $this->assertSame(['GET', 'HEAD'], $route->methods());

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('review_ppt', $middleware);
        }
    }
}
