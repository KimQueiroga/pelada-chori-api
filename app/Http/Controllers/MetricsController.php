<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MetricsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = config('metrics.token');
        if ($token) {
            $bearer = $request->bearerToken();
            if (! hash_equals($token, (string) $bearer)) {
                return response('Unauthorized', 401);
            }
        }

        $definitions = [
            'http_requests_total' => [
                'type' => 'counter',
                'help' => 'Total HTTP requests processed.',
            ],
            'http_request_duration_ms' => [
                'type' => 'histogram',
                'help' => 'HTTP request duration in milliseconds.',
            ],
        ];

        $lines = [];
        foreach ($definitions as $name => $meta) {
            $lines[] = '# HELP '.$name.' '.$meta['help'];
            $lines[] = '# TYPE '.$name.' '.$meta['type'];
        }

        $rows = DB::table('metric_samples')
            ->orderBy('name')
            ->orderBy('key')
            ->get(['name', 'labels', 'value']);

        foreach ($rows as $row) {
            $labels = [];
            if ($row->labels) {
                $decoded = json_decode($row->labels, true);
                if (is_array($decoded)) {
                    $labels = $decoded;
                }
            }

            $labelText = '';
            if (! empty($labels)) {
                $pairs = [];
                foreach ($labels as $k => $v) {
                    $pairs[] = $k.'="'.self::escapeLabel($v).'"';
                }
                $labelText = '{'.implode(',', $pairs).'}';
            }

            $lines[] = $row->name.$labelText.' '.$row->value;
        }

        $body = implode("\n", $lines)."\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }

    private static function escapeLabel(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("\n", '\\n', $value);
        return str_replace('"', '\\"', $value);
    }
}
