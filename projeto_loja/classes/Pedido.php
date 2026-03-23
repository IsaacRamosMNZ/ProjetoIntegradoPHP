<?php

declare(strict_types=1);

require_once __DIR__ . '/Cliente.php';
require_once __DIR__ . '/Produto.php';

class Pedido
{
    private int $numero;
    private Cliente $cliente;
    /** @var array<int, array{produto: Produto, quantidade: int}> */
    private array $itens;

    public function __construct(int $numero, Cliente $cliente)
    {
        $this->setNumero($numero);
        $this->setCliente($cliente);
        $this->itens = [];
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): void
    {
        $this->numero = $numero;
    }

    public function getCliente(): Cliente
    {
        return $this->cliente;
    }

    public function setCliente(Cliente $cliente): void
    {
        $this->cliente = $cliente;
    }

    /** @return array<int, array{produto: Produto, quantidade: int}> */
    public function getItens(): array
    {
        return $this->itens;
    }

    public function adicionarProduto(Produto $produto, int $quantidade = 1): void
    {
        if ($quantidade < 1) {
            throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
        }

        $this->itens[] = [
            'produto' => $produto,
            'quantidade' => $quantidade,
        ];
    }

    public function calcularTotal(): float
    {
        $total = 0.0;

        foreach ($this->itens as $item) {
            $total += $item['produto']->getPreco() * $item['quantidade'];
        }

        return $total;
    }

    public function exibirResumo(): string
    {
        $linhasProdutos = '';

        foreach ($this->itens as $item) {
            $produto = $item['produto'];
            $quantidade = $item['quantidade'];
            $nome = htmlspecialchars($produto->getNome(), ENT_QUOTES, 'UTF-8');
            $precoUnitario = number_format($produto->getPreco(), 2, ',', '.');
            $subtotal = number_format($produto->getPreco() * $quantidade, 2, ',', '.');
            $linhasProdutos .= "<li><span>{$nome} x {$quantidade}<small>Unit.: R$ {$precoUnitario}</small></span><strong>R$ {$subtotal}</strong></li>";
        }

        if ($linhasProdutos === '') {
            $linhasProdutos = '<li><span>Nenhum produto adicionado.</span></li>';
        }

        $clienteNome = htmlspecialchars($this->cliente->getNome(), ENT_QUOTES, 'UTF-8');
        $clienteEmail = htmlspecialchars($this->cliente->getEmail(), ENT_QUOTES, 'UTF-8');
        $total = number_format($this->calcularTotal(), 2, ',', '.');

        return "
            <section class=\"card\">
                <h2>Pedido N&ordm; {$this->numero}</h2>
                <div class=\"bloco\">
                    <h3>Cliente</h3>
                    <p><strong>{$clienteNome}</strong></p>
                    <p>{$clienteEmail}</p>
                </div>
                <div class=\"bloco\">
                    <h3>Produtos</h3>
                    <ul class=\"lista-produtos\">
                        {$linhasProdutos}
                    </ul>
                </div>
                <p class=\"total\">Total do Pedido: <span>R$ {$total}</span></p>
            </section>
        ";
    }
}
