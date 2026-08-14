<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdditionalStateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_state_admin_can_create_an_additional_admin(): void
    {
        $primary = User::factory()->create([
            'role' => 'state_admin',
            'is_primary_state_admin' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($primary)->post(route('admin.additional-state-admins.store'), [
            'name' => 'Nishkarsh (E.D)',
            'email' => 'nishkarsh.mehra@pwc.com',
            'password' => 'test-password-123',
        ]);

        $response->assertRedirect(route('admin.additional-state-admins.index'));

        $additional = User::query()->where('email', 'nishkarsh.mehra@pwc.com')->firstOrFail();
        $this->assertSame('state_admin', $additional->role);
        $this->assertFalse($additional->is_primary_state_admin);
        $this->assertTrue($additional->is_active);
        $this->assertSame('Executive Director', $additional->designationRecord?->name);
        $this->assertTrue(Hash::check('test-password-123', $additional->password));
    }

    public function test_additional_admin_has_operational_access_but_not_primary_authority(): void
    {
        $designation = Designation::query()->create(['name' => 'Executive Director', 'sort_order' => 2]);
        $additional = User::factory()->create([
            'role' => 'state_admin',
            'is_primary_state_admin' => false,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $this->actingAs($additional)
            ->get(route('admin.service-catalog.index'))
            ->assertOk();

        $this->actingAs($additional)
            ->get(route('admin.additional-state-admins.index'))
            ->assertForbidden();

        $this->actingAs($additional)
            ->get(route('admin.designations.index'))
            ->assertForbidden();
    }

    public function test_primary_account_cannot_be_managed_as_an_additional_admin(): void
    {
        $primary = User::factory()->create([
            'role' => 'state_admin',
            'is_primary_state_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($primary)
            ->get(route('admin.additional-state-admins.edit', $primary))
            ->assertNotFound();
    }
}
