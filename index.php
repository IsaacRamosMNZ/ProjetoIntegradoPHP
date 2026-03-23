<?php

declare(strict_types=1);

require_once 'models/Cliente.php';
require_once 'models/Produto.php';
require_once 'models/Pedido.php';
require_once 'dao/PedidoDAO.php';

$nomeCliente = '';
$emailCliente = '';
$enderecoCliente = '';
$mensagemErro = '';
$mensagemSucesso = '';
$mensagemAviso = '';
$pedido = null;
$bloquearEdicao = false;

$itens = [
    ['nome' => '', 'quantidade' => '1', 'preco' => ''],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = $_POST['acao'] ?? 'salvar';
    $nomeCliente = trim($_POST['nome'] ?? '');
    $emailCliente = trim($_POST['email'] ?? '');
    $enderecoCliente = trim($_POST['endereco'] ?? '');
    $itensNomes = $_POST['item_nome'] ?? [];
    $itensQuantidades = $_POST['item_quantidade'] ?? [];
    $itensPrecos = $_POST['item_preco'] ?? [];

    $itens = [];
    $totalLinhas = max(count($itensNomes), count($itensQuantidades), count($itensPrecos), 1);
    for ($i = 0; $i < $totalLinhas; $i++) {
        $itens[] = [
            'nome' => trim((string) ($itensNomes[$i] ?? '')),
            'quantidade' => trim((string) ($itensQuantidades[$i] ?? '1')),
            'preco' => trim((string) ($itensPrecos[$i] ?? '')),
        ];
    }

    if ($acao === 'editar') {
        $bloquearEdicao = false;
    } else {
        $bloquearEdicao = true;
    }

    $produtosParaPedido = [];

    if ($acao !== 'editar') {
        foreach ($itens as $item) {
            $nomeItem = $item['nome'];
            $quantidadeInformada = $item['quantidade'];
            $precoInformado = $item['preco'];

            if ($nomeItem === '' && $precoInformado === '') {
                continue;
            }

            if ($nomeItem === '' || $precoInformado === '' || $quantidadeInformada === '') {
                $mensagemErro = 'Preencha nome, quantidade e valor em todos os itens informados.';
                break;
            }

            $quantidade = (int) $quantidadeInformada;
            if ((string) $quantidade !== $quantidadeInformada || $quantidade < 1) {
                $mensagemErro = 'A quantidade deve ser um numero inteiro maior que zero.';
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
                'quantidade' => $quantidade,
                'preco' => $preco,
            ];
        }

        if ($nomeCliente === '' || $emailCliente === '' || $enderecoCliente === '') {
            $mensagemErro = 'Preencha nome, e-mail e endereco para cadastrar o cliente.';
        } elseif (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
            $mensagemErro = 'Informe um e-mail valido.';
        } elseif (count($produtosParaPedido) === 0) {
            $mensagemErro = 'Cadastre pelo menos um item para gerar o pedido.';
        }

        if ($mensagemErro !== '') {
            $bloquearEdicao = false;
        } else {
            try {
                $cliente = new Cliente(0, $nomeCliente, $emailCliente, $enderecoCliente);
                $pedidoDao = new PedidoDAO();
                $pedido = $pedidoDao->salvarPedido($cliente, $produtosParaPedido);
                $mensagemSucesso = 'Pedido salvo com sucesso.';
            } catch (Throwable $exception) {
                error_log('Falha ao salvar pedido: ' . $exception->getMessage());
                $mensagemErro = 'Nao foi possivel salvar no banco de dados. Confirme se o MySQL do XAMPP esta iniciado.';
                $bloquearEdicao = false;
            }
        }
    }
}

$camposDesabilitados = $bloquearEdicao ? 'disabled' : '';
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
            <form method="post" action="" id="form-pedido">
                <div class="form-grid">
                    <label class="campo">
                        <span>Nome do cliente</span>
                        <input type="text" name="nome"
                            value="<?php echo htmlspecialchars($nomeCliente, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: Joao Silva" <?php echo $camposDesabilitados; ?> required>
                    </label>

                    <label class="campo">
                        <span>E-mail</span>
                        <input type="email" name="email"
                            value="<?php echo htmlspecialchars($emailCliente, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: joao@email.com" <?php echo $camposDesabilitados; ?> required>
                    </label>

                    <label class="campo campo-largura-total">
                        <span>Endereco</span>
                        <input type="text" name="endereco"
                            value="<?php echo htmlspecialchars($enderecoCliente, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: Rua A, 120 - Centro" <?php echo $camposDesabilitados; ?> required>
                    </label>
                </div>

                <h3 class="subsecao">Itens para venda</h3>
                <div class="itens-grid itens-grid-cabecalho">
                    <span>Nome do item</span>
                    <span>Quantidade</span>
                    <span>Valor (R$)</span>
                </div>

                <div id="itens-wrapper">
                    <?php foreach ($itens as $item): ?>
                        <div class="itens-grid item-linha">
                            <label class="campo">
                                <input type="text" name="item_nome[]"
                                    value="<?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Ex.: Teclado Mecanico" <?php echo $camposDesabilitados; ?>>
                            </label>
                            <label class="campo campo-quantidade">
                                <input type="number" name="item_quantidade[]"
                                    value="<?php echo htmlspecialchars($item['quantidade'], ENT_QUOTES, 'UTF-8'); ?>"
                                    min="1" step="1" placeholder="1" <?php echo $camposDesabilitados; ?>>
                            </label>
                            <label class="campo">
                                <input type="text" name="item_preco[]"
                                    value="<?php echo htmlspecialchars($item['preco'], ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Ex.: 250,00" <?php echo $camposDesabilitados; ?>>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="acoes-form">
                    <button class="botao botao-secundario" type="button" id="btn-adicionar-item" <?php echo $camposDesabilitados; ?>>Adicionar Item</button>
                    <?php if ($bloquearEdicao): ?>
                        <button class="botao" type="submit" name="acao" value="editar">Editar</button>
                    <?php else: ?>
                        <button class="botao" type="submit" name="acao" value="salvar">Cadastrar e Gerar Pedido</button>
                    <?php endif; ?>
                </div>

                <template id="template-item">
                    <div class="itens-grid item-linha">
                        <label class="campo">
                            <input type="text" name="item_nome[]" placeholder="Ex.: Teclado Mecanico">
                        </label>
                        <label class="campo campo-quantidade">
                            <input type="number" name="item_quantidade[]" min="1" step="1" value="1" placeholder="1">
                        </label>
                        <label class="campo">
                            <input type="text" name="item_preco[]" placeholder="Ex.: 250,00">
                        </label>
                    </div>
                </template>
            </form>

            <?php if ($mensagemErro !== ''): ?>
                <p class="erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if ($mensagemSucesso !== ''): ?>
                <p class="sucesso"><?php echo htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if ($mensagemAviso !== ''): ?>
                <p class="aviso"><?php echo htmlspecialchars($mensagemAviso, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </section>

        <?php if ($pedido instanceof Pedido): ?>
            <?php echo $pedido->exibirResumo(); ?>
        <?php else: ?>
            <p class="aviso">Preencha cliente e itens para visualizar o pedido completo.</p>
        <?php endif; ?>

    </main>
</body>

<script>
    (function () {
        const botaoAdicionar = document.getElementById('btn-adicionar-item');
        const itensWrapper = document.getElementById('itens-wrapper');
        const templateItem = document.getElementById('template-item');

        if (!botaoAdicionar || !itensWrapper || !templateItem || botaoAdicionar.disabled) {
            return;
        }

        botaoAdicionar.addEventListener('click', function () {
            const clone = templateItem.content.cloneNode(true);
            itensWrapper.appendChild(clone);
        });
    })();
</script>

</html>