<?php

declare(strict_types=1);

require_once 'classes/Cliente.php';
require_once 'classes/Produto.php';
require_once 'classes/Pedido.php';

$nomeCliente = '';
$emailCliente = '';
$mensagemErro = '';
$pedido = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeCliente = trim($_POST['nome'] ?? '');
    $emailCliente = trim($_POST['email'] ?? '');

    if ($nomeCliente === '' || $emailCliente === '') {
        $mensagemErro = 'Preencha nome e e-mail para cadastrar o cliente.';
    } elseif (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = 'Informe um e-mail valido.';
    } else {
        $cliente = new Cliente(1, $nomeCliente, $emailCliente);

        $produto1 = new Produto(1, 'Notebook', 3500.00);
        $produto2 = new Produto(2, 'Mouse Gamer', 150.00);
        $produto3 = new Produto(3, 'Headset', 280.00);

        $pedido = new Pedido(1001, $cliente);
        $pedido->adicionarProduto($produto1);
        $pedido->adicionarProduto($produto2);
        $pedido->adicionarProduto($produto3);
    }
}
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
            <p class="subtitulo">Cadastre o cliente e gere o resumo do pedido automaticamente.</p>
        </header>

        <section class="card form-card">
            <h2>Cadastrar Cliente</h2>
            <form method="post" action="">
                <div class="form-grid">
                    <label class="campo">
                        <span>Nome do cliente</span>
                        <input type="text" name="nome"
                            value="<?php echo htmlspecialchars($nomeCliente, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: Joao Silva" required>
                    </label>

                    <label class="campo">
                        <span>E-mail</span>
                        <input type="email" name="email"
                            value="<?php echo htmlspecialchars($emailCliente, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: joao@email.com" required>
                    </label>
                </div>

                <button class="botao" type="submit">Cadastrar Cliente e Gerar Pedido</button>
            </form>

            <?php if ($mensagemErro !== ''): ?>
                <p class="erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </section>

        <?php if ($pedido instanceof Pedido): ?>
            <?php echo $pedido->exibirResumo(); ?>
        <?php else: ?>
            <p class="aviso">Preencha os dados acima para visualizar o pedido com o cliente cadastrado.</p>
        <?php endif; ?>
    </main>
</body>

</html>