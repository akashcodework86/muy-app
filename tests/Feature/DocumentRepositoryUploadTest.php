<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRepositoryUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_includes_upload_progress_script(): void
    {
        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.documents.create'))
            ->assertOk()
            ->assertSee('data-doc-upload-progress', false)
            ->assertSee('bindUploadProgress', false)
            ->assertSee('XMLHttpRequest', false);
    }

    public function test_store_returns_json_for_ajax_upload(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $file = UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf');

        $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->post(route('admin.documents.store'), [
                'title' => 'Policy note',
                'allowed_roles' => ['state_admin'],
                'file' => $file,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Document uploaded.',
            ]);
    }

    public function test_store_returns_json_when_accept_header_is_json(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'state_admin', 'is_active' => true]);
        $file = UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf');

        $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post(route('admin.documents.store'), [
                'title' => 'Policy note',
                'allowed_roles' => ['state_admin'],
                'file' => $file,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Document uploaded.',
            ]);
    }
}
