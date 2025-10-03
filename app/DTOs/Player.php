<?php
namespace App\DTOs;

class Player
{
    public int $id;         // ← vou carregar o id para persistir depois
    public string $nome;
    public float $media;    // média geral (0..5), pode vir de votos
    public string $posicao; // 'DEFESA' | 'MEIO' | 'ATAQUE'

    public function __construct(int $id, string $nome, float $media, string $posicao)
    {
        $this->id = $id;
        $this->nome = $nome;
        $this->media = $media;
        $this->posicao = $posicao; // já em MAIÚSCULO
    }
}
