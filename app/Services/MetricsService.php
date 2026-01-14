<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class MetricsService
{
    private const HISTOGRAM_BUCKETS_MS = [
        5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000,
    ];

    public static function increment(string $name, array $labels = [], float $value = 1.0): void
    {
        $labels = self::normalizeLabels($labels);
        $key = self::buildKey($name, $labels);
        $hash = hash('sha256', $key);
        $now = now();

        $updated = DB::table('metric_samples')
            ->where('key_hash', $hash)
            ->update([
                'value' => DB::raw('value + '.(float) $value),
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        try {
            DB::table('metric_samples')->insert([
                'name' => $name,
                'key_hash' => $hash,
                'key' => $key,
                'labels' => empty($labels) ? null : json_encode($labels),
                'value' => (float) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $e) {
            DB::table('metric_samples')
                ->where('key_hash', $hash)
                ->update([
                    'value' => DB::raw('value + '.(float) $value),
                    'updated_at' => $now,
                ]);
        }
    }

    public static function observeHistogram(string $name, array $labels, float $valueMs): void
    {
        $buckets = self::HISTOGRAM_BUCKETS_MS;
        foreach ($buckets as $bucket) {
            if ($valueMs <= $bucket) {
                self::increment($name.'_bucket', array_merge($labels, [
                    'le' => (string) $bucket,
                ]));
            }
        }

        self::increment($name.'_bucket', array_merge($labels, ['le' => '+Inf']));
        self::increment($name.'_sum', $labels, $valueMs);
        self::increment($name.'_count', $labels, 1);
    }

    private static function normalizeLabels(array $labels): array
    {
        $clean = [];
        foreach ($labels as $key => $value) {
            if ($value === null) {
                continue;
            }
            $clean[(string) $key] = (string) $value;
        }
        ksort($clean);
        return $clean;
    }

    private static function buildKey(string $name, array $labels): string
    {
        if (empty($labels)) {
            return $name;
        }

        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = $key.'='.$value;
        }
        return $name.'|'.implode('|', $parts);
    }
}
