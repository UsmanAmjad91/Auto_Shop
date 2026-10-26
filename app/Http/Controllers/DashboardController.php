<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request, AttendanceService $attendance)
    {
        $user = $request->user();

        return view('dashboard', [
            'cards' => $this->dashboard->cards($user),
            'alerts' => $this->dashboard->alerts($user),
            'activity' => $this->dashboard->activity($user),
            'today' => $user->employee ? $attendance->today($user->employee) : null,
            'layout' => settings('theme.dashboard_layout', 'standard'),
        ]);
    }

    /** AJAX refresh of cards, charts, alerts and activity. */
    public function data(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'cards' => $this->dashboard->cards($user),
            'charts' => $this->dashboard->charts($user),
            'alerts' => $this->dashboard->alerts($user),
            'activity' => $this->dashboard->activity($user),
            'updated_at' => now()->format('H:i:s'),
        ]);
    }
}
