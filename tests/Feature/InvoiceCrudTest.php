<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);

        // Create a test invoice
        $this->invoice = Invoice::query()->create([
            'invoice_number' => 'INV-TEST-999',
            'package_name' => 'Test Package Plan',
            'amount' => 120000.00,
            'status' => 'paid',
            'billing_date' => '2026-06-25',
            'due_date' => '2026-07-25',
            'payment_method' => 'bank_transfer',
        ]);
    }

    public function test_guests_cannot_access_invoices(): void
    {
        $response = $this->get('/vi/admin/invoices');
        $response->assertRedirect('/login');
    }

    public function test_users_without_admin_role_cannot_access_invoices(): void
    {
        $customer = User::factory()->create([
            'role_id' => null,
        ]);

        $this->actingAs($customer);

        $response = $this->get('/vi/admin/invoices');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_invoices_listing(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/invoices');
        $response->assertOk();
        $response->assertViewIs('admin.invoices.index');
        $response->assertViewHas('invoices');

        $invoices = $response->viewData('invoices');
        $this->assertTrue($invoices->contains($this->invoice));
    }

    public function test_admin_can_view_invoice_details(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get('/vi/admin/invoices/' . $this->invoice->id);
        $response->assertOk();
        $response->assertViewIs('admin.invoices.show');
        $response->assertViewHas('invoice');
        
        $response->assertSee('INV-TEST-999');
        $response->assertSee('Test Package Plan');
        $response->assertSee('120.000 ₫');
    }

    public function test_admin_can_filter_and_search_invoices(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($admin);

        // Create another pending invoice
        $pendingInvoice = Invoice::query()->create([
            'invoice_number' => 'INV-PENDING-111',
            'package_name' => 'Pending Package Plan',
            'amount' => 150000.00,
            'status' => 'pending',
            'billing_date' => '2026-06-25',
            'due_date' => '2026-07-25',
            'payment_method' => null,
        ]);

        // 1. Search by invoice number
        $responseSearch = $this->get('/vi/admin/invoices?q=INV-TEST-999');
        $responseSearch->assertOk();
        $invoicesSearch = $responseSearch->viewData('invoices');
        $this->assertTrue($invoicesSearch->contains($this->invoice));
        $this->assertFalse($invoicesSearch->contains($pendingInvoice));

        // 2. Filter by status = pending
        $responseFilter = $this->get('/vi/admin/invoices?status=pending');
        $responseFilter->assertOk();
        $invoicesFilter = $responseFilter->viewData('invoices');
        $this->assertTrue($invoicesFilter->contains($pendingInvoice));
        $this->assertFalse($invoicesFilter->contains($this->invoice));
    }
}
