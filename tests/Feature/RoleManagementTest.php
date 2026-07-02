<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_editor_can_access_content_sections(): void
    {
        $this->actingAs($this->editor())->get('/admin/products')->assertOk();
        $this->actingAs($this->editor())->get('/admin/blogs')->assertOk();
        $this->actingAs($this->editor())->get('/admin/dashboard')->assertOk();
    }

    public function test_editor_cannot_access_admin_only_sections(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->get('/admin/users')->assertRedirect('/');
        $this->actingAs($editor)->get('/admin/newsletter')->assertRedirect('/');
        $this->actingAs($editor)->get('/admin/query-form')->assertRedirect('/');
    }

    public function test_customer_cannot_access_panel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/products')->assertRedirect('/');
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/admin/users/{$target->id}/role", ['role' => 'editor']);

        $response->assertOk();
        $this->assertSame('editor', $target->fresh()->role);
    }

    public function test_editor_cannot_change_roles(): void
    {
        $editor = $this->editor();
        $target = User::factory()->create();

        $this->actingAs($editor)
            ->patch("/admin/users/{$target->id}/role", ['role' => 'editor'])
            ->assertRedirect('/');
        $this->assertSame('user', $target->fresh()->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patchJson("/admin/users/{$admin->id}/role", ['role' => 'user']);

        $response->assertStatus(422);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_invalid_role_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/admin/users/{$target->id}/role", ['role' => 'superuser'])
            ->assertStatus(422);
    }

    public function test_editor_dashboard_has_no_query_emails(): void
    {
        $q = new \App\Models\QueryForm;
        $q->email = 'secret@customer.com';
        $q->save();

        $response = $this->actingAs($this->editor())->get('/admin/dashboard');

        $response->assertOk();
        $response->assertDontSee('secret@customer.com');
    }
}
