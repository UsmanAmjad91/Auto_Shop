<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        return ['cards' => collect($dashboard->cards($request->user()))->map(fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'value' => $c['raw']]),
            'alerts' => $dashboard->alerts($request->user())];
    }
}
