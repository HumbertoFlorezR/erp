<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Renderiza el componente Vue pasando los datos necesarios
        return Inertia::render('Tenant/Dashboard');
    }
}
