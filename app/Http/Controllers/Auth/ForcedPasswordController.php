<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForcedPasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcedPasswordController extends Controller
{
    /**
     * Show the required password change page.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/ChangePassword', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Replace the temporary password.
     */
    public function update(ForcedPasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password changed successfully.')]);

        return to_route('dashboard');
    }
}
