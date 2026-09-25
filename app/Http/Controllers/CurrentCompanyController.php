<?php

namespace App\Http\Controllers;

use App\Http\Requests\SwitchCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CurrentCompanyController extends Controller
{
    public function update(SwitchCompanyRequest $request, Company $company): RedirectResponse
    {
        $request->session()->put('current_company_id', $company->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company switched.')]);

        return back();
    }
}
