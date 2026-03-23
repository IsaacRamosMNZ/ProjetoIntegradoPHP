<?php

declare(strict_types=1);

require_once 'classes/Cliente.php';
require_once 'classes/Produto.php';
require_once 'classes/Pedido.php';

$nomeCliente = '';
$emailCliente = '';
$mensagemErro = '';
$pedido = null;
$itensNomes = ['', '', ''];
$itensPrecos = ['', '', ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeCliente = trim($_POST['nome'] ?? '');
    $emailCliente = trim($_POST['email'] ?? '');
    $itensNomes = $_POST['item_nome'] ?? ['', '', ''];
    $itensPrecos = $_POST['item_preco'] ?? ['', '', ''];
    $produtosParaPedido = [];

    $limite = max(count($itensNomes), count($itensPrecos));
    for ($i = 0; $i < $limite; $i++) {
        $nomeItem = trim((string) ($itensNomes[$i] ?? ''));
        $precoInformado = trim((string) ($itensPrecos[$i] ?? ''));

        if ($nomeItem === '' && $precoInformado === '') {
            continue;
        }

        if ($nomeItem === '' || $precoInformado === '') {
            $mensagemErro = 'Preencha nome e valor em todos os itens informados.';
            break;
        }

        $precoNormalizado = str_replace(',', '.', $precoInformado);
        if (!is_numeric($precoNormalizado)) {
            $mensagemErro = 'Valor invalido. Use apenas numeros (ex.: 150 ou 150,90).';
            break;
        }

        $preco = (float) $precoNormalizado;
        if ($preco < 0) {
            $mensagemErro = 'O valor do item nao pode ser negativo.';
            break;
        }

        $produtosParaPedido[] = [
            'nome' => $nomeItem,
            'preco' => $preco,
        ];
    }

    if ($nomeCliente === '' || $emailCliente === '') {
        $mensagemErro = 'Preencha nome e e-mail para cadastrar o cliente.';
    } elseif (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = 'Informe um e-mail valido.';
    } elseif (count($produtosParaPedido) === 0) {
        $mensagemErro = 'Cadastre pelo menos um item para gerar o pedido.';
    } else {
        $cliente = new Cliente(1, $nomeCliente, $emailCliente);

        $pedido = new Pedido(1001, $cliente);
        foreach ($produtosParaPedido as $indice => $item) {
            $produto = new Produto($indice + 1, $item['nome'], $item['preco']);
            $pedido->adicionarProduto($produto);
        }
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
            <p class="subtitulo">Cadastre cliente e itens para venda e gere o pedido automaticamente.</p>
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

                <h3 class="subsecao">Itens para venda</h3>
                <div class="itens-grid itens-grid-cabecalho">
                    <span>Nome do item</span>
                    <span>Valor (R$)</span>
                </div>

                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="itens-grid">
                        <label class="campo">
                            <input type="text" name="item_nome[]"
                                value="<?php echo htmlspecialchars(trim((string) ($itensNomes[$i] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Ex.: Teclado Mecanico">
                        </label>
                        <label class="campo">
                            <input type="text" name="item_preco[]"
                                value="<?php echo htmlspecialchars(trim((string) ($itensPrecos[$i] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Ex.: 250,00">
                        </label>
                    </div>
                <?php endfor; ?>

                <button class="botao" type="submit">Cadastrar e Gerar Pedido</button>
            </form>

            <?php if ($mensagemErro !== ''): ?>
                <p class="erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </section>

        <?php if ($pedido instanceof Pedido): ?>
            <?php echo $pedido->exibirResumo(); ?>
        <?php else: ?>
            <p class="aviso">Preencha cliente e itens para visualizar o pedido completo.</p>
        <?php endif; ?>
    </main>
</body>

</html>