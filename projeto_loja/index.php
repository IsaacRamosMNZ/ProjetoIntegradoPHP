<?php

declare(strict_types=1);

require_once 'classes/Cliente.php';
require_once 'classes/Produto.php';
require_once 'classes/Pedido.php';

// Instancia os objetos principais do sistema.
$cliente = new Cliente(1, 'Joao Silva', 'joao@email.com');

$produto1 = new Produto(1, 'Notebook', 3500.00);
$produto2 = new Produto(2, 'Mouse Gamer', 150.00);
$produto3 = new Produto(3, 'Headset', 280.00);

$pedido = new Pedido(1001, $cliente);
$pedido->adicionarProduto($produto1);
$pedido->adicionarProduto($produto2);
$pedido->adicionarProduto($produto3);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Pedidos da Loja</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="container">
        <header class="hero">
            <p class="selo">Projeto Integrador em PHP</p>
            <h1>Sistema de Pedidos da Loja</h1>
            <p class="subtitulo">Resumo organizado de cliente, produtos e valor total do pedido.</p>
        </header>

        <?php echo $pedido->exibirResumo(); ?>
    </main>
</body>

</html>