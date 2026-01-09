<?php

namespace App\Services;

use App\Models\Partida;
use App\Models\PartidaSubstituicao;
use App\Models\SorteioTime;
use Illuminate\Support\Collection;

class PartidaElencoService
{
    public static function substituicoesAtivas(Partida $partida): Collection
    {
        return PartidaSubstituicao::with(['jogadorSai.user','jogadorEntra.user'])
            ->where('partida_id', $partida->id)
            ->whereNull('revertida_em')
            ->get();
    }

    public static function elencoAtivo(Partida $partida): array
    {
        $partida->load([
            'timeA.jogadores.jogador.user',
            'timeB.jogadores.jogador.user',
        ]);

        $subs = self::substituicoesAtivas($partida);

        $timeA = $partida->timeA;
        $timeB = $partida->timeB;

        return [
            'time_a' => [
                'id' => $timeA?->id,
                'nome' => $timeA?->nome ?? 'Time A',
                'jogadores' => self::montarElencoTime($timeA, $subs),
            ],
            'time_b' => [
                'id' => $timeB?->id,
                'nome' => $timeB?->nome ?? 'Time B',
                'jogadores' => self::montarElencoTime($timeB, $subs),
            ],
            'substituicoes' => $subs->map(function (PartidaSubstituicao $s) {
                return [
                    'id' => $s->id,
                    'time_id' => $s->time_id,
                    'jogador_sai' => $s->jogadorSai,
                    'jogador_entra' => $s->jogadorEntra,
                    'created_at' => $s->created_at,
                ];
            })->values(),
        ];
    }

    public static function idsAtivos(array $elenco): array
    {
        $idsA = array_values(array_unique(array_map(
            fn ($p) => $p['jogador_id'] ?? null,
            $elenco['time_a']['jogadores'] ?? []
        )));
        $idsB = array_values(array_unique(array_map(
            fn ($p) => $p['jogador_id'] ?? null,
            $elenco['time_b']['jogadores'] ?? []
        )));
        $idsA = array_values(array_filter($idsA));
        $idsB = array_values(array_filter($idsB));

        return [
            'time_a' => $idsA,
            'time_b' => $idsB,
            'all' => array_values(array_unique(array_merge($idsA, $idsB))),
        ];
    }

    private static function montarElencoTime(?SorteioTime $time, Collection $subs): array
    {
        if (!$time) return [];

        $base = $time->jogadores
            ->map(function ($tj) {
                return [
                    'jogador_id' => $tj->jogador_id,
                    'jogador' => $tj->jogador,
                ];
            })
            ->keyBy('jogador_id');

        foreach ($subs as $s) {
            if ((int)$s->time_id !== (int)$time->id) continue;
            $base->forget($s->jogador_sai_id);
            if ($s->jogadorEntra) {
                $base[$s->jogador_entra_id] = [
                    'jogador_id' => $s->jogador_entra_id,
                    'jogador' => $s->jogadorEntra,
                ];
            }
        }

        return $base->values()->all();
    }
}
