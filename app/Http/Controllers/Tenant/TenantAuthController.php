<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TenantAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return Inertia::render('Tenant/Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $subdominio = $request->route('tenant');
        $dbName = 'tenant_' . str_replace('-', '_', $subdominio);

        config(['database.connections.mysql.database' => $dbName]);
        DB::purge('mysql');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Ahora la sesión SÍ se regenerará sobre una cookie real y válida
            $request->session()->regenerate();

            // Redirección nativa limpia de Inertia hacia el Dashboard
            return redirect()->intended(route('tenant.dashboard', ['tenant' => $subdominio]));
        }

        throw ValidationException::withMessages([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ]);
    }

    public function logout(Request $request)
    {
        $subdominio = $request->route('tenant');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', ['tenant' => $subdominio]);
    }
}
