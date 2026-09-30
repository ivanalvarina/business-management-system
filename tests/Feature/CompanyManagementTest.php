<?php

use App\Models\Company;
use App\Models\QuotationTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('users without permission cannot access company management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('companies.index'))
        ->assertForbidden();
});

test('authorized users can create companies and assign user access', function () {
    Storage::fake('public');

    $admin = userWithPermissions(['companies.create', 'companies.view', 'companies.access-all']);
    $assignedUser = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('companies.store'), [
            'company_code' => 'ACME',
            'company_name' => 'Acme Corporation',
            'trade_name' => 'Acme',
            'tin' => '123-456-789-000',
            'email' => 'hello@acme.test',
            'phone' => '555-1000',
            'address' => 'Main Office',
            'purchasing_assistant_name' => 'Harold Asuncion',
            'corporate_sales_manager_name' => 'Rose Paguia',
            'quotation_template_name' => '40FIED PDF Template',
            'quotation_template_file' => UploadedFile::fake()->create('template.pdf', 128, 'application/pdf'),
            'quotation_template_config' => '{"fields":{"quotation_no":{"page":1,"x":540,"y":120}}}',
            'status' => Company::STATUS_ACTIVE,
            'logo' => UploadedFile::fake()->image('logo.png'),
            'user_ids' => [$assignedUser->id],
        ])
        ->assertRedirect();

    $company = Company::where('company_code', 'ACME')->firstOrFail();

    expect($company->company_name)->toBe('Acme Corporation')
        ->and($company->purchasing_assistant_name)->toBe('Harold Asuncion')
        ->and($company->corporate_sales_manager_name)->toBe('Rose Paguia')
        ->and($company->activeQuotationTemplate)->toBeInstanceOf(QuotationTemplate::class)
        ->and($company->activeQuotationTemplate->name)->toBe('40FIED PDF Template')
        ->and($company->activeQuotationTemplate->config['fields']['quotation_no']['x'])->toBe(540)
        ->and($company->users()->whereKey($assignedUser->id)->exists())->toBeTrue()
        ->and($company->logo)->not->toBeNull();

    Storage::disk('public')->assertExists($company->logo);
    Storage::disk('public')->assertExists($company->activeQuotationTemplate->stored_path);
});

test('company code must be unique', function () {
    $admin = userWithPermissions(['companies.create', 'companies.access-all']);
    Company::factory()->create(['company_code' => 'ACME']);

    $this->actingAs($admin)
        ->post(route('companies.store'), [
            'company_code' => 'ACME',
            'company_name' => 'Duplicate Company',
            'status' => Company::STATUS_ACTIVE,
            'user_ids' => [],
        ])
        ->assertSessionHasErrors('company_code');
});

test('scoped users only see assigned companies', function () {
    $user = userWithPermissions('companies.view');
    $assignedCompany = Company::factory()->create(['company_name' => 'Assigned Company']);
    $unassignedCompany = Company::factory()->create(['company_name' => 'Hidden Company']);
    $user->companies()->attach($assignedCompany);

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertSee('Assigned Company')
        ->assertDontSee('Hidden Company');

    $this->actingAs($user)
        ->get(route('companies.show', $assignedCompany))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('companies.show', $unassignedCompany))
        ->assertForbidden();
});

test('authorized users can update deactivate and delete companies', function () {
    Storage::fake('public');

    $admin = userWithPermissions([
        'companies.view',
        'companies.edit',
        'companies.delete',
        'companies.access-all',
    ]);
    $company = Company::factory()->create([
        'company_code' => 'OLD',
        'company_name' => 'Old Company',
    ]);

    $this->actingAs($admin)
        ->put(route('companies.update', $company), [
            'company_code' => 'NEW',
            'company_name' => 'New Company',
            'purchasing_assistant_name' => 'Updated Buyer',
            'corporate_sales_manager_name' => 'Updated Manager',
            'quotation_template_name' => 'LEEPE PDF Template',
            'quotation_template_file' => UploadedFile::fake()->create('leepe-template.pdf', 128, 'application/pdf'),
            'quotation_template_config' => '{"fields":{"customer_name":{"page":1,"x":120,"y":250}}}',
            'status' => Company::STATUS_ACTIVE,
            'user_ids' => [],
        ])
        ->assertRedirect(route('companies.index'));

    expect($company->fresh()->company_code)->toBe('NEW')
        ->and($company->fresh()->purchasing_assistant_name)->toBe('Updated Buyer')
        ->and($company->fresh()->corporate_sales_manager_name)->toBe('Updated Manager')
        ->and($company->fresh()->activeQuotationTemplate->name)->toBe('LEEPE PDF Template');

    $this->actingAs($admin)
        ->patch(route('companies.deactivate', $company))
        ->assertRedirect();

    expect($company->fresh()->status)->toBe(Company::STATUS_INACTIVE);

    $this->actingAs($admin)
        ->delete(route('companies.destroy', $company))
        ->assertRedirect(route('companies.index'));

    expect(Company::find($company->id))->toBeNull();
});

test('company update preserves existing logo when no replacement is uploaded', function () {
    Storage::fake('public');

    $admin = userWithPermissions([
        'companies.view',
        'companies.edit',
        'companies.access-all',
    ]);
    Storage::disk('public')->put('company-logos/existing.png', 'existing logo');
    $company = Company::factory()->create([
        'logo' => 'company-logos/existing.png',
    ]);

    $this->actingAs($admin)
        ->put(route('companies.update', $company), [
            'company_code' => $company->company_code,
            'company_name' => 'Logo Preserved Company',
            'status' => Company::STATUS_ACTIVE,
            'user_ids' => [],
        ])
        ->assertRedirect(route('companies.index'));

    expect($company->fresh()->logo)->toBe('company-logos/existing.png');
    Storage::disk('public')->assertExists('company-logos/existing.png');
});

test('company switching rejects unauthorized and inactive companies', function () {
    $user = userWithPermissions('dashboard.view');
    $assignedCompany = Company::factory()->create();
    $unassignedCompany = Company::factory()->create();
    $inactiveCompany = Company::factory()->inactive()->create();
    $user->companies()->attach([$assignedCompany->id, $inactiveCompany->id]);

    $this->actingAs($user)
        ->patch(route('current-company.update', $assignedCompany))
        ->assertRedirect()
        ->assertSessionHas('current_company_id', $assignedCompany->id);

    $this->actingAs($user)
        ->patch(route('current-company.update', $unassignedCompany))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('current-company.update', $inactiveCompany))
        ->assertForbidden();
});
