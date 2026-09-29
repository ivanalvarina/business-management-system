<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Support\AuditLogger;

test('audit trail is permission protected', function () {
    $user = userWithPermissions('dashboard.view');

    $this->actingAs($user)
        ->get(route('audit-logs.index'))
        ->assertForbidden();
});

test('authorized users can filter audit logs', function () {
    $viewer = userWithPermissions('audit-logs.view');
    $actor = userWithPermissions('dashboard.view');
    AuditLog::factory()->create([
        'user_id' => $actor->id,
        'module' => 'companies',
        'action' => 'created',
        'new_values' => ['company_name' => 'Visible Company'],
    ]);
    AuditLog::factory()->create([
        'module' => 'vendors',
        'action' => 'deleted',
        'old_values' => ['vendor_name' => 'Hidden Vendor'],
    ]);

    $this->actingAs($viewer)
        ->get(route('audit-logs.index', [
            'module' => 'companies',
            'action' => 'created',
            'user_id' => $actor->id,
        ]))
        ->assertOk()
        ->assertSee('companies')
        ->assertSee('created')
        ->assertDontSee('Hidden Vendor');
});

test('sensitive audit values are redacted', function () {
    $user = userWithPermissions('audit-logs.view');

    app(AuditLogger::class)->record(
        module: 'users',
        action: 'updated',
        newValues: [
            'name' => 'Safe Name',
            'password' => 'secret-password',
            'api_token' => 'secret-token',
            'nested' => ['two_factor_secret' => 'secret-2fa'],
        ],
        request: request(),
    );

    $log = AuditLog::query()->firstOrFail();

    expect($log->new_values)
        ->toMatchArray([
            'name' => 'Safe Name',
            'password' => '[redacted]',
            'api_token' => '[redacted]',
            'nested' => ['two_factor_secret' => '[redacted]'],
        ]);

    $this->actingAs($user)
        ->get(route('audit-logs.index', ['search' => 'users']))
        ->assertOk()
        ->assertDontSee('secret-password')
        ->assertDontSee('secret-token')
        ->assertDontSee('secret-2fa');
});

test('company updates create audit records with old and new values', function () {
    $user = userWithPermissions(['companies.edit', 'companies.view']);
    $company = Company::factory()->create();
    $company->users()->attach($user);

    $this->actingAs($user)
        ->put(route('companies.update', $company), [
            'company_code' => $company->company_code,
            'company_name' => 'Updated Company',
            'trade_name' => $company->trade_name,
            'tin' => $company->tin,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'status' => Company::STATUS_ACTIVE,
            'user_ids' => [$user->id],
        ])
        ->assertRedirect();

    $log = AuditLog::query()
        ->where('module', 'companies')
        ->where('action', 'updated')
        ->firstOrFail();

    expect($log->subject_id)->toBe($company->id)
        ->and($log->old_values['company_name'])->toBe($company->company_name)
        ->and($log->new_values['company_name'])->toBe('Updated Company');
});
