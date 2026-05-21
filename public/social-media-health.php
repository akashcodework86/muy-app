<?php

declare(strict_types=1);

/**
 * TEMPORARY diagnostic for social media posts 500 errors.
 *
 * Open:
 *   /phase3/social-media-health.php?key=akash
 *
 * Delete this file after fixing production.
 */

$expectedKey = 'akash';
$providedKey = isset($_GET['key']) ? (string) $_GET['key'] : '';

header('Content-Type: text/plain; charset=utf-8');

if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$root = __DIR__;
for ($i = 0; $i < 8; $i++) {
    if (is_file($root.'/artisan')) {
        break;
    }
    $parent = dirname($root);
    if ($parent === $root) {
        break;
    }
    $root = $parent;
}

echo "Laravel root: {$root}\n\n";

$files = [
    'app/Http/Controllers/SocialMediaPostController.php',
    'app/Services/SocialMediaPostPreviewService.php',
    'app/Support/SocialMediaPostAccess.php',
    'app/Support/SocialMediaPostPlatforms.php',
    'app/Models/SocialMediaPost.php',
    'config/social_media_posts.php',
    'routes/web.php',
    'resources/views/social-media-posts/form.blade.php',
    'resources/views/social-media-posts/partials/preview-script.blade.php',
];

foreach ($files as $relative) {
    $path = $root.'/'.$relative;
    echo ($relative.': '.(is_file($path) ? 'OK' : 'MISSING'))."\n";
}

echo "\n--- bootstrap ---\n";

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    echo "Bootstrap: OK\n";
    echo 'Route spoc.social-media-posts.index: '.(Illuminate\Support\Facades\Route::has('spoc.social-media-posts.index') ? 'OK' : 'MISSING')."\n";
    echo 'Route spoc.social-media-posts.create: '.(Illuminate\Support\Facades\Route::has('spoc.social-media-posts.create') ? 'OK' : 'MISSING')."\n";
    echo 'Class SocialMediaPostPreviewService: '.(class_exists(App\Services\SocialMediaPostPreviewService::class) ? 'OK' : 'MISSING')."\n";

    $schema = Illuminate\Support\Facades\Schema::class;
    echo 'Table social_media_posts: '.($schema::hasTable('social_media_posts') ? 'OK' : 'MISSING')."\n";
    if ($schema::hasTable('social_media_posts')) {
        foreach (['posted_platforms', 'platform', 'thumbnail_url', 'preview_title'] as $column) {
            echo "  column {$column}: ".($schema::hasColumn('social_media_posts', $column) ? 'OK' : 'MISSING')."\n";
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Bootstrap ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
