<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Produto.php';
require_once __DIR__ . '/../models/Pedido.php';

class PedidoDAO
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $database->ensureSchema($this->conn);
    }

    /**
     * @param array<int, array{nome: string, quantidade: int, preco: float}> $itens
     */
    public function salvarPedido(Cliente $cliente, array $itens): Pedido
    {
        if (count($itens) === 0) {
            throw new InvalidArgumentException('O pedido precisa de pelo menos um item.');
        }

        $this->conn->beginTransaction();

        try {
            $clienteId = $this->inserirCliente($cliente);
            $numeroPedido = $this->proximoNumeroPedido();
            $totalPedido = $this->calcularTotal($itens);

            $stmtPedido = $this->conn->prepare(
                'INSERT INTO pedidos (numero, cliente_id, total) VALUES (:numero, :cliente_id, :total)'
            );
            $stmtPedido->bindValue(':numero', $numeroPedido, PDO::PARAM_INT);
            $stmtPedido->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $stmtPedido->bindValue(':total', $totalPedido);
            $stmtPedido->execute();

            $pedidoId = (int) $this->conn->lastInsertId();
            $pedido = new Pedido(
                $numeroPedido,
                new Cliente($clienteId, $cliente->getNome(), $cliente->getEmail(), $cliente->getEndereco())
            );

            foreach ($itens as $item) {
                $produtoId = $this->buscarOuInserirProduto($item['nome'], $item['preco']);
                $subtotal = $item['preco'] * $item['quantidade'];

                $stmtItem = $this->conn->prepare(
                    'INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
                     VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal)'
                );
                $stmtItem->bindValue(':pedido_id', $pedidoId, PDO::PARAM_INT);
                $stmtItem->bindValue(':produto_id', $produtoId, PDO::PARAM_INT);
                $stmtItem->bindValue(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmtItem->bindValue(':preco_unitario', $item['preco']);
                $stmtItem->bindValue(':subtotal', $subtotal);
                $stmtItem->execute();

                $produto = new Produto($produtoId, $item['nome'], $item['preco']);
                $pedido->adicionarProduto($produto, $item['quantidade']);
            }

            $this->conn->commit();

            return $pedido;
        } catch (Throwable $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $exception;
        }
    }

    private function inserirCliente(Cliente $cliente): int
    {
        $stmt = $this->conn->prepare('INSERT INTO clientes (nome, email, endereco) VALUES (:nome, :email, :endereco)');
        $stmt->bindValue(':nome', $cliente->getNome());
        $stmt->bindValue(':email', $cliente->getEmail());
        $stmt->bindValue(':endereco', $cliente->getEndereco());
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    /**
     * @return array<int, array{pedido_numero: int, cliente_nome: string, cliente_email: string, endereco: string, item_nome: string, quantidade: int, preco_unitario: float, subtotal: float}>
     */
    public function listarClientesComCompras(): array
    {
        $sql = 'SELECT
                    p.numero AS pedido_numero,
                    c.nome AS cliente_nome,
                    c.email AS cliente_email,
                    c.endereco AS endereco,
                    pr.nome AS item_nome,
                    pi.quantidade AS quantidade,
                    pi.preco_unitario AS preco_unitario,
                    pi.subtotal AS subtotal
                FROM pedidos p
                INNER JOIN clientes c ON c.id = p.cliente_id
                INNER JOIN pedido_itens pi ON pi.pedido_id = p.id
                INNER JOIN produtos pr ON pr.id = pi.produto_id
                ORDER BY p.id DESC, pi.id ASC';

        $stmt = $this->conn->query($sql);
        $linhas = $stmt->fetchAll();

        return is_array($linhas) ? $linhas : [];
    }

    private function proximoNumeroPedido(): int
    {
        $stmt = $this->conn->query('SELECT COALESCE(MAX(numero), 1000) + 1 AS proximo FROM pedidos');
        $resultado = $stmt->fetch();

        return (int) ($resultado['proximo'] ?? 1001);
    }

    private function calcularTotal(array $itens): float
    {
        $total = 0.0;

        foreach ($itens as $item) {
            $total += $item['preco'] * $item['quantidade'];
        }

        return $total;
    }

    private function buscarOuInserirProduto(string $nome, float $preco): int
    {
        $stmtBusca = $this->conn->prepare('SELECT id FROM produtos WHERE nome = :nome AND preco = :preco LIMIT 1');
        $stmtBusca->bindValue(':nome', $nome);
        $stmtBusca->bindValue(':preco', $preco);
        $stmtBusca->execute();

        $produto = $stmtBusca->fetch();
        if ($produto !== false) {
            return (int) $produto['id'];
        }

        $stmtInsert = $this->conn->prepare('INSERT INTO produtos (nome, preco) VALUES (:nome, :preco)');
        $stmtInsert->bindValue(':nome', $nome);
        $stmtInsert->bindValue(':preco', $preco);
        $stmtInsert->execute();

        return (int) $this->conn->lastInsertId();
    }
}
