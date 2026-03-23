<?php

declare(strict_types=1);

require_once 'dao/PedidoDAO.php';

$mensagemErro = '';
$listaClientesCompras = [];

try {
    $pedidoDao = new PedidoDAO();
    $listaClientesCompras = $pedidoDao->listarClientesComCompras();
} catch (Throwable $exception) {
    error_log('Falha ao listar clientes e compras: ' . $exception->getMessage());
    $mensagemErro = 'Nao foi possivel carregar a listagem de compras.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes e Compras</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="container">
        <header class="hero">
            <p class="selo">Projeto Integrador em PHP</p>
            <h1>Clientes e Compras</h1>
            <p class="subtitulo">Listagem de clientes, enderecos e itens comprados.</p>
        </header>

        <section class="card tabela-card">
            <?php if ($mensagemErro !== ''): ?>
                <p class="erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php elseif (count($listaClientesCompras) > 0): ?>
                <div class="tabela-responsiva">
                    <table class="tabela-compras">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>E-mail</th>
                                <th>Endereco</th>
                                <th>Item</th>
                                <th>Qtd.</th>
                                <th>Preco item</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaClientesCompras as $linha): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $linha['pedido_numero'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $linha['cliente_nome'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $linha['cliente_email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $linha['endereco'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $linha['item_nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $linha['quantidade'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>R$ <?php echo number_format((float) $linha['preco_unitario'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format((float) $linha['subtotal'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="aviso">Nenhuma compra registrada ainda.</p>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>