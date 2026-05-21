<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Data dummy untuk initial render — Firebase JS akan update real-time
        $latest = (object) [
            'suhu' => 0,
            'kelembapan' => 0,
            'soil' => 0,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        $timeseries = [];

        return view('dashboard', compact('latest', 'timeseries'));
    }
}
