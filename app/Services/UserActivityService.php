<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UserActivityService
{
    public static function record(int $userId, string $userName): void
    {
        $today = now()->toDateString();
        $now = now();

        $updated = DB::table('user_activity_daily')
            ->where('activity_date', $today)
            ->where('user_id', $userId)
            ->update([
                'user_name' => $userName,
                'requests_count' => DB::raw('requests_count + 1'),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        try {
            DB::table('user_activity_daily')->insert([
                'activity_date' => $today,
                'user_id' => $userId,
                'user_name' => $userName,
                'requests_count' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $e) {
            DB::table('user_activity_daily')
                ->where('activity_date', $today)
                ->where('user_id', $userId)
                ->update([
                    'user_name' => $userName,
                    'requests_count' => DB::raw('requests_count + 1'),
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    public static function activeUsersCount(int $days): int
    {
        $days = max(1, $days);
        $startDate = now()->subDays($days - 1)->toDateString();

        return DB::table('user_activity_daily')
            ->where('activity_date', '>=', $startDate)
            ->distinct('user_id')
            ->count('user_id');
    }

    public static function requestsTotalForDate(string $date): int
    {
        return (int) DB::table('user_activity_daily')
            ->where('activity_date', $date)
            ->sum('requests_count');
    }
}
