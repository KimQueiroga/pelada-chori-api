<?php
namespace App\Services;

use App\DTOs\Player;
use App\DTOs\Team;

class DrawService
{
    /**
     * Sorteio 1 (restritivo)
     * - ≥1 DEF/MEI/ATA por time
     * - Top3 e Bottom3 separados
     * - Diferença de médias ≤ $limit
     * - Anti–3 DEF/ATA (máx. 2 por time)
     * - Swap corretivo com MEI mais próximo
     */
    public function makeSorteio1(array $players, int $qtTimes, int $qtPorTime, float $limit = 0.10, int $tries = 300000): array
    {
        $this->assertCapacity($players, $qtTimes, $qtPorTime);

        [$low3, $high3] = $this->extremesSets($players);

        for ($i = 0; $i < $tries; $i++) {
            $teams = $this->buildRandomTeams($players, $qtTimes, $qtPorTime);
            if (!$this->allHaveCore($teams)) continue;
            if (!$this->antiThreeAll($teams)) continue;
            if (!$this->extremesSeparated($teams, $low3, $high3)) continue;

            if (!$this->diffOk($teams, $limit)) continue;

            // correções finais
            $teams = $this->fixAntiThreeViaSwap($teams);
            if (!$this->allHaveCore($teams) || !$this->antiThreeAll($teams) || !$this->diffOk($teams, $limit)) continue;

            return $teams;
        }
        throw new \RuntimeException('Não consegui gerar o sorteio 1 dentro dos limites.');
    }

    /**
     * Sorteio 2 (aleatório com restrições)
     * - ≥1 DEF/MEI/ATA por time
     * - Diferença de médias ≤ $limit
     * - Anti–3 DEF/ATA + swap corretivo
     */
    public function makeSorteio2(array $players, int $qtTimes, int $qtPorTime, float $limit = 0.10, int $tries = 300000): array
    {
        $this->assertCapacity($players, $qtTimes, $qtPorTime);

        for ($i = 0; $i < $tries; $i++) {
            $teams = $this->buildRandomTeams($players, $qtTimes, $qtPorTime);
            if (!$this->allHaveCore($teams)) continue;
            if (!$this->antiThreeAll($teams)) continue;
            if (!$this->diffOk($teams, $limit)) continue;

            $teams = $this->fixAntiThreeViaSwap($teams);
            if (!$this->allHaveCore($teams) || !$this->antiThreeAll($teams) || !$this->diffOk($teams, $limit)) continue;

            return $teams;
        }
        throw new \RuntimeException('Não consegui gerar o sorteio 2 dentro dos limites.');
    }

    /** ----------------- Helpers de critério ----------------- */

    private function assertCapacity(array $players, int $qtTimes, int $qtPorTime): void
    {
        $cap = $qtTimes * $qtPorTime;
        if (count($players) !== $cap) {
            throw new \InvalidArgumentException("Quantidade de jogadores (".count($players).") precisa ser exatamente igual à capacidade ($cap).");
        }
    }

    private function extremesSets(array $players): array
    {
        $sorted = $players;
        usort($sorted, fn($a,$b) => $a->media <=> $b->media);
        $low3 = array_map(fn($p)=>$p->nome, array_slice($sorted, 0, 3));
        $high3 = array_map(fn($p)=>$p->nome, array_slice($sorted, -3, 3));
        return [array_flip($low3), array_flip($high3)];
    }

    private function extremesSeparated(array $teams, array $low3, array $high3): bool
    {
        $cover = function(array $set) use ($teams) {
            $seen = [];
            foreach ($teams as $idx => $t) {
                foreach ($t->players as $p) {
                    if (isset($set[$p->nome])) { $seen[$idx] = true; break; }
                }
            }
            return count($seen) === count($teams);
        };
        return $cover($low3) && $cover($high3);
    }

    private function allHaveCore(array $teams): bool
    {
        foreach ($teams as $t) if (!$t->hasCore()) return false;
        return true;
    }

    private function antiThreeAll(array $teams): bool
    {
        foreach ($teams as $t) {
            if ($t->countBy('DEFESA') > 2) return false;
            if ($t->countBy('ATAQUE') > 2) return false;
        }
        return true;
    }

    private function diffOk(array $teams, float $limit): bool
    {
        $avgs = array_map(fn($t) => $t->avg(), $teams);
        return (max($avgs) - min($avgs)) <= $limit + 1e-9;
    }

    /** --------------- Construção de times --------------- */

    private function buildRandomTeams(array $players, int $qtTimes, int $qtPorTime): array
    {
        $pool = $players;
        shuffle($pool);

        $teams = [];
        $offset = 0;
        for ($i = 0; $i < $qtTimes; $i++) {
            $teams[$i] = new Team();
            for ($k = 0; $k < $qtPorTime; $k++) {
                $teams[$i]->add($pool[$offset++]);
            }
        }
        return $teams;
    }

    /** --------------- Correções anti–3 DEF/ATA via swap --------------- */

    private function fixAntiThreeViaSwap(array $teams): array
    {
        foreach (['DEFESA','ATAQUE'] as $pos) {
            foreach ($teams as $i => $t) {
                if ($t->countBy($pos) >= 3) {
                    // pega excedente de menor média
                    $excedentes = array_values(array_filter($t->players, fn($p)=>$p->posicao===$pos));
                    usort($excedentes, fn($a,$b)=>$a->media <=> $b->media);
                    $ex = $excedentes[0];

                    // busca MEI mais próximo em outros times
                    $best = null;
                    foreach ($teams as $j => $t2) {
                        if ($j === $i) continue;
                        foreach ($t2->players as $cand) {
                            if ($cand->posicao !== 'MEIO') continue;
                            $delta = abs($cand->media - $ex->media);
                            if (!$best || $delta < $best['delta']) {
                                $best = ['j'=>$j, 'cand'=>$cand, 'delta'=>$delta];
                            }
                        }
                    }
                    if ($best) {
                        $this->swap($teams[$i], $teams[$best['j']], $ex, $best['cand']);
                        if (!$this->allHaveCore($teams) || !$this->antiThreeAll($teams)) {
                            // desfaz se quebrou regras
                            $this->swap($teams[$i], $teams[$best['j']], $best['cand'], $ex);
                        }
                    }
                }
            }
        }
        return $teams;
    }

    private function swap(Team $a, Team $b, Player $pa, Player $pb): void
    {
        $a->players = array_values(array_filter($a->players, fn($x)=>$x!==$pa));
        $b->players = array_values(array_filter($b->players, fn($x)=>$x!==$pb));
        $a->players[] = $pb;
        $b->players[] = $pa;
    }
}
