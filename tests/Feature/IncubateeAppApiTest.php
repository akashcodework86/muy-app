<?php

namespace Tests\Feature;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Hub;
use App\Models\MentorshipRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncubateeAppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_incubatee_can_login_with_mobile_and_receive_token(): void
    {
        [$user] = $this->seedIncubatee();

        $this->postJson('/api/incubatee/login', [
            'phone' => '9876543210',
            'password' => '9876543210',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.phone', '9876543210')
            ->assertJsonPath('user.name', 'Priya Rawat')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'first_name']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'incubatee-app',
        ]);
    }

    public function test_login_rejects_bad_password(): void
    {
        $this->seedIncubatee();

        $this->postJson('/api/incubatee/login', [
            'phone' => '9876543210',
            'password' => 'wrong',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_staff_cannot_use_incubatee_app_login(): void
    {
        User::factory()->create([
            'role' => 'state_admin',
            'phone' => '9876543210',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->postJson('/api/incubatee/login', [
            'phone' => '9876543210',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_dashboard_requires_token(): void
    {
        $this->getJson('/api/incubatee/dashboard')->assertUnauthorized();
    }

    public function test_incubatee_can_load_dashboard(): void
    {
        [$user, $cfa] = $this->seedIncubatee();
        Sanctum::actingAs($user);

        $this->getJson('/api/incubatee/dashboard')
            ->assertOk()
            ->assertJsonPath('profile.application_no', $cfa->application_no)
            ->assertJsonPath('profile.business', 'Food Processing')
            ->assertJsonPath('stats.mentorship_count', 0)
            ->assertJsonPath('journey.0.key', 'cfa')
            ->assertJsonPath('journey.0.status', 'done');
    }

    public function test_incubatee_can_request_and_cancel_mentorship(): void
    {
        Notification::fake();
        [$user] = $this->seedIncubatee();
        Sanctum::actingAs($user);

        $this->postJson('/api/incubatee/mentorship', [
            'category' => 'financial',
            'comment' => 'Need help with Mudra loan',
        ])
            ->assertCreated()
            ->assertJsonPath('request.category', 'financial')
            ->assertJsonPath('request.status', MentorshipRequest::STATUS_PENDING)
            ->assertJsonPath('request.status_label', 'Pending')
            ->assertJsonPath('request.can_cancel', true);

        $requestId = MentorshipRequest::query()->sole()->id;

        $this->getJson('/api/incubatee/mentorship')
            ->assertOk()
            ->assertJsonPath('requests.0.id', $requestId);

        $this->postJson('/api/incubatee/mentorship/'.$requestId.'/cancel')
            ->assertOk()
            ->assertJsonPath('request.status', MentorshipRequest::STATUS_CANCELLED);

        $this->assertSame(MentorshipRequest::STATUS_CANCELLED, MentorshipRequest::query()->find($requestId)?->status);
    }

    public function test_logout_revokes_token(): void
    {
        [$user] = $this->seedIncubatee();
        $token = $user->createToken('incubatee-app')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/incubatee/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_incubatee_can_load_learn_catalog(): void
    {
        [$user] = $this->seedIncubatee();
        Sanctum::actingAs($user);

        $this->getJson('/api/incubatee/learn')
            ->assertOk()
            ->assertJsonPath('categories.0.slug', 'starting-business')
            ->assertJsonPath('categories.0.videos.0.url', 'https://www.youtube.com/playlist?list=PLGd-uiTAXX5o')
            ->assertJsonStructure([
                'categories' => [
                    ['slug', 'title', 'title_hi', 'videos' => [['youtube_id', 'url']]],
                ],
                'resources',
            ]);
    }

    public function test_incubatee_sees_only_allowed_documents_and_can_download(): void
    {
        Storage::fake('local');
        [$user] = $this->seedIncubatee();
        Sanctum::actingAs($user);

        Storage::disk('local')->put('library/guide.pdf', 'pdf-bytes');

        $visible = Document::query()->create([
            'title' => 'Incubatee handbook',
            'allowed_roles' => [Document::ROLE_INCUBATEE],
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $visible->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => 'library/guide.pdf',
            'original_name' => 'handbook.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 9,
        ]);
        $visible->update(['latest_version_id' => $version->id]);

        $hidden = Document::query()->create([
            'title' => 'Staff only',
            'allowed_roles' => [Document::ROLE_STATE_ADMIN],
        ]);
        $hiddenVersion = DocumentVersion::query()->create([
            'document_id' => $hidden->id,
            'version_no' => 1,
            'disk' => 'local',
            'path' => 'library/guide.pdf',
            'original_name' => 'staff.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 9,
        ]);
        $hidden->update(['latest_version_id' => $hiddenVersion->id]);

        $this->getJson('/api/incubatee/documents')
            ->assertOk()
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.title', 'Incubatee handbook');

        $this->get('/api/incubatee/documents/'.$visible->id.'/download')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->getJson('/api/incubatee/documents/'.$hidden->id.'/download')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: CfaSubmission}
     */
    private function seedIncubatee(): array
    {
        $hub = Hub::query()->create(['slug' => 'app-hub', 'name' => 'Dehradun Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'app-district',
            'name' => 'Dehradun',
            'sort_order' => 1,
        ]);
        $cfa = CfaSubmission::query()->create([
            'district_id' => $district->id,
            'applicant_name' => 'Priya Rawat',
            'phone' => '9876543210',
            'application_no' => 'MUY/2025/1842',
            'payload' => [
                'email' => 'priya@example.com',
                'form_stage' => 'Early stage',
                'product' => 'Pickle',
                'business_category' => 'Food Processing',
            ],
        ]);
        $user = User::factory()->create([
            'role' => 'incubatee',
            'name' => 'Priya Rawat',
            'phone' => '9876543210',
            'password' => '9876543210',
            'cfa_submission_id' => $cfa->id,
            'district_id' => $district->id,
            'is_active' => true,
        ]);

        return [$user, $cfa];
    }
}
