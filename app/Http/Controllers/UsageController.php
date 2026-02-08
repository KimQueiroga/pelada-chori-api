<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $date = $request->query('date');
        try {
            $date = $date
                ? Carbon::createFromFormat('Y-m-d', $date)->toDateString()
                : now()->toDateString();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid date format. Use YYYY-MM-DD.',
            ], 422);
        }

        $rows = DB::table('user_activity_daily')
            ->where('activity_date', $date)
            ->orderByDesc('requests_count')
            ->get([
                'user_id',
                'user_name',
                'requests_count',
                'first_seen_at',
                'last_seen_at',
            ]);

        $totalUsers = $rows->count();
        $totalRequests = $rows->sum('requests_count');

        return response()->json([
            'date' => $date,
            'total_users' => $totalUsers,
            'total_requests' => $totalRequests,
            'users' => $rows,
        ]);
    }
}
