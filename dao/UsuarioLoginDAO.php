<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

class UsuarioLoginDAO
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $database->ensureSchema($this->conn);
    }

    public function inserir(
        string $email,
        string $cpf,
        string $telefone,
        string $whatsapp,
        string $senha
    ): bool {
        $sql = 'INSERT INTO usuarios_login (email, cpf, telefone, whatsapp, senha_hash)
                VALUES (:email, :cpf, :telefone, :whatsapp, :senha_hash)';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':cpf', $cpf);
        $stmt->bindValue(':telefone', $telefone);
        $stmt->bindValue(':whatsapp', $whatsapp);
        $stmt->bindValue(':senha_hash', password_hash($senha, PASSWORD_DEFAULT));

        return $stmt->execute();
    }

    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        $sql = 'SELECT u.id, u.email, u.cpf, u.telefone, u.whatsapp, u.criado_em,
                       (SELECT COUNT(*) FROM pedidos_login p WHERE p.usuario_id = u.id) AS total_pedidos
                FROM usuarios_login u
                ORDER BY u.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($dados) ? $dados : [];
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT id, email, cpf, telefone, whatsapp, criado_em
                FROM usuarios_login
                WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        return $dados === false ? null : $dados;
    }

    public function atualizar(
        int $id,
        string $email,
        string $cpf,
        string $telefone,
        string $whatsapp,
        string $novaSenha
    ): bool {
        if ($novaSenha !== '') {
            $sql = 'UPDATE usuarios_login
                    SET email = :email, cpf = :cpf, telefone = :telefone, whatsapp = :whatsapp, senha_hash = :senha_hash
                    WHERE id = :id';

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':senha_hash', password_hash($novaSenha, PASSWORD_DEFAULT));
        } else {
            $sql = 'UPDATE usuarios_login
                    SET email = :email, cpf = :cpf, telefone = :telefone, whatsapp = :whatsapp
                    WHERE id = :id';

            $stmt = $this->conn->prepare($sql);
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':cpf', $cpf);
        $stmt->bindValue(':telefone', $telefone);
        $stmt->bindValue(':whatsapp', $whatsapp);

        return $stmt->execute();
    }

    public function excluir(int $id): bool
    {
        $this->conn->beginTransaction();

        try {
            $stmtItens = $this->conn->prepare(
                'DELETE i
                 FROM pedido_itens_login i
                 INNER JOIN pedidos_login p ON p.id = i.pedido_id
                 WHERE p.usuario_id = :usuario_id'
            );
            $stmtItens->bindValue(':usuario_id', $id, PDO::PARAM_INT);
            $stmtItens->execute();

            $stmtPedidos = $this->conn->prepare('DELETE FROM pedidos_login WHERE usuario_id = :usuario_id');
            $stmtPedidos->bindValue(':usuario_id', $id, PDO::PARAM_INT);
            $stmtPedidos->execute();

            $stmtUsuario = $this->conn->prepare('DELETE FROM usuarios_login WHERE id = :id');
            $stmtUsuario->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtUsuario->execute();

            $this->conn->commit();
            return $stmtUsuario->rowCount() > 0;
        } catch (Throwable $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $exception;
        }
    }
}
