<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Cliente.php';

class ClienteDAO
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $database->ensureSchema($this->conn);
    }

    public function inserir(Cliente $cliente): bool
    {
        $sql = 'INSERT INTO clientes (nome, email, endereco)
                VALUES (:nome, :email, :endereco)';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':nome', $cliente->getNome());
        $stmt->bindValue(':email', $cliente->getEmail());
        $stmt->bindValue(':endereco', $cliente->getEndereco());

        return $stmt->execute();
    }

    /** @return array<int, array{id: int, nome: string, email: string, endereco: string, criado_em: string}> */
    public function listar(): array
    {
        $sql = 'SELECT id, nome, email, endereco, criado_em FROM clientes ORDER BY id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($dados) ? $dados : [];
    }

    public function buscarPorId(int $id): ?Cliente
    {
        $sql = 'SELECT id, nome, email, endereco FROM clientes WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {
            return null;
        }

        return new Cliente(
            (int) $dados['id'],
            (string) $dados['nome'],
            (string) $dados['email'],
            (string) $dados['endereco']
        );
    }

    public function atualizar(Cliente $cliente): bool
    {
        $sql = 'UPDATE clientes
                SET nome = :nome, email = :email, endereco = :endereco
                WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':nome', $cliente->getNome());
        $stmt->bindValue(':email', $cliente->getEmail());
        $stmt->bindValue(':endereco', $cliente->getEndereco());
        $stmt->bindValue(':id', $cliente->getId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function excluir(int $id): bool
    {
        $sql = 'DELETE FROM clientes WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
