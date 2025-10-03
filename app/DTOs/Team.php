<?php
namespace App\DTOs;

class Team
{
    /** @var Player[] */
    public array $players = [];

    public function add(Player $p): void
    {
        $this->players[] = $p;
    }

    public function avg(): float
    {
        $n = count($this->players);
        if (!$n) return 0.0;
        $sum = 0.0;
        foreach ($this->players as $p) $sum += $p->media;
        return $sum / $n;
    }

    public function countBy(string $pos): int
    {
        $c = 0;
        foreach ($this->players as $p) if ($p->posicao === $pos) $c++;
        return $c;
    }

    public function hasCore(): bool
    {
        return $this->countBy('DEFESA') > 0
            && $this->countBy('MEIO')   > 0
            && $this->countBy('ATAQUE') > 0;
    }
}
