<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_admin_can_open_migration_ops_page(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.ops.migrations'))
            ->assertOk()
            ->assertSee('Run pending migrations')
            ->assertSee('Social media posts');
    }

    public function test_non_admin_cannot_open_migration_ops_page(): void
    {
        $staff = User::factory()->create(['role' => 'district_staff', 'is_active' => true]);

        $this->actingAs($staff)
            ->get(route('admin.ops.migrations'))
            ->assertForbidden();
    }

    public function test_state_admin_can_download_sql_bundle(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.ops.migrations.sql', 'social_media_posts'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_state_admin_can_run_pending_migrations(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.ops.migrations.run'), ['confirm' => '1'])
            ->assertOk()
            ->assertSee('Migrations completed');
    }
}
