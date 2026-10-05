<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ConfirmPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.passwords.confirm');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']]);
        $request->session()->regenerate();
        $request->session()->passwordConfirmed();

        return redirect()->to(route('profileedit').'#security');
    }
}
