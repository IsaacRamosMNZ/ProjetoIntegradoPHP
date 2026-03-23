<?php

declare(strict_types=1);

class Cliente
{
    private int $id;
    private string $nome;
    private string $email;
    private string $endereco;

    public function __construct(int $id, string $nome, string $email, string $endereco)
    {
        $this->setId($id);
        $this->setNome($nome);
        $this->setEmail($email);
        $this->setEndereco($endereco);
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = trim($email);
    }

    public function getEndereco(): string
    {
        return $this->endereco;
    }

    public function setEndereco(string $endereco): void
    {
        $this->endereco = trim($endereco);
    }
}
