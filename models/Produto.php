<?php

declare(strict_types=1);

class Produto
{
    private int $id;
    private string $nome;
    private float $preco;

    public function __construct(int $id, string $nome, float $preco)
    {
        $this->setId($id);
        $this->setNome($nome);
        $this->setPreco($preco);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): void
    {
        $this->nome = trim($nome);
    }

    public function getPreco(): float
    {
        return $this->preco;
    }

    public function setPreco(float $preco): void
    {
        if ($preco < 0) {
            throw new InvalidArgumentException('O preco nao pode ser negativo.');
        }

        $this->preco = $preco;
    }
}
