<?php

use App\Models\Company;
use App\Models\PayrollHoliday;

test('authorized company users can manage payroll holidays', function () {
    $user = userWithPermissions(['payrolls.create']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->post(route('companies.payroll-holidays.store', $company), [
            'date' => '2026-11-30',
            'name' => 'Bonifacio Day',
        ])
        ->assertRedirect();

    $holiday = PayrollHoliday::query()->sole();

    expect($holiday->company_id)->toBe($company->id)
        ->and($holiday->date->toDateString())->toBe('2026-11-30')
        ->and($holiday->name)->toBe('Bonifacio Day');

    $this->actingAs($user)
        ->delete(route('companies.payroll-holidays.destroy', [$company, $holiday]))
        ->assertRedirect();

    expect(PayrollHoliday::query()->count())->toBe(0);
});

test('users cannot manage another company payroll calendar', function () {
    $user = userWithPermissions(['payrolls.create']);
    $company = Company::factory()->create();

    $this->actingAs($user)
        ->post(route('companies.payroll-holidays.store', $company), [
            'date' => '2026-11-30',
            'name' => 'Bonifacio Day',
        ])
        ->assertForbidden();
});

test('a company cannot have two payroll holidays on the same date', function () {
    $user = userWithPermissions(['payrolls.create']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    PayrollHoliday::factory()->for($company)->create(['date' => '2026-11-30']);

    $this->actingAs($user)
        ->post(route('companies.payroll-holidays.store', $company), [
            'date' => '2026-11-30',
            'name' => 'Duplicate holiday',
        ])
        ->assertSessionHasErrors('date');
});
