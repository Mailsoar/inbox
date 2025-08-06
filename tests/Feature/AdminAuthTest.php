<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que la page de login admin est accessible
     */
    public function test_admin_login_page_is_accessible()
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertSee('Administration');
    }

    /**
     * Test que les routes admin sont protégées
     */
    public function test_admin_routes_are_protected()
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    /**
     * Test qu'un admin connecté peut accéder au dashboard
     */
    public function test_authenticated_admin_can_access_dashboard()
    {
        $admin = AdminUser::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'is_active' => true
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }

    /**
     * Test qu'un admin inactif ne peut pas se connecter
     */
    public function test_inactive_admin_cannot_access_dashboard()
    {
        $admin = AdminUser::create([
            'name' => 'Inactive Admin',
            'email' => 'inactive@example.com',
            'is_active' => false
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    /**
     * Test de déconnexion admin
     */
    public function test_admin_can_logout()
    {
        $admin = AdminUser::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'is_active' => true
        ]);

        $this->actingAs($admin, 'admin');
        
        $response = $this->post('/admin/auth/logout');
        $response->assertRedirect('/admin/login');
        
        // Vérifier que l'admin est déconnecté
        $this->assertGuest('admin');
    }
}