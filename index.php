<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/Database.php';

function normalizarCpf(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function normalizarPreco(string $valor): ?float
{
    $valor = str_replace(',', '.', trim($valor));
    if ($valor === '' || !is_numeric($valor)) {
        return null;
    }

    $numero = (float) $valor;
    if ($numero < 0) {
        return null;
    }

    return $numero;
}

function normalizarTelefone(string $telefone): string
{
    return preg_replace('/\D+/', '', $telefone) ?? '';
}

function telefoneComCodigoPaisValido(string $telefone): bool
{
    return (bool) preg_match('/^55\d{10,11}$/', $telefone);
}

if (!isset($_SESSION['carrinho_itens']) || !is_array($_SESSION['carrinho_itens'])) {
    $_SESSION['carrinho_itens'] = [];
}

$mensagemErro = '';
$mensagemSucesso = '';

$emailForm = trim((string) ($_POST['email'] ?? ''));
$cpfForm = trim((string) ($_POST['cpf'] ?? ''));
$telefoneForm = trim((string) ($_POST['telefone'] ?? ''));
$whatsappForm = trim((string) ($_POST['whatsapp'] ?? ''));
$itemNomeForm = trim((string) ($_POST['item_nome'] ?? ''));
$itemQuantidadeForm = trim((string) ($_POST['item_quantidade'] ?? '1'));
$itemPrecoForm = trim((string) ($_POST['item_preco'] ?? ''));
$enderecoForm = trim((string) ($_POST['endereco_entrega'] ?? ''));
$cidadeForm = trim((string) ($_POST['cidade'] ?? ''));
$cepForm = trim((string) ($_POST['cep'] ?? ''));
$obsForm = trim((string) ($_POST['observacoes'] ?? ''));

$db = new Database();
$conn = $db->getConnection();
$db->ensureSchema($conn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'logout') {
        unset($_SESSION['usuario']);
        $_SESSION['carrinho_itens'] = [];
        header('Location: index.php');
        exit;
    }

    if ($acao === 'cadastro') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $cpf = normalizarCpf((string) ($_POST['cpf'] ?? ''));
        $telefone = normalizarTelefone((string) ($_POST['telefone'] ?? ''));
        $whatsapp = normalizarTelefone((string) ($_POST['whatsapp'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');

        if ($email === '' || $cpf === '' || $telefone === '' || $whatsapp === '' || $senha === '') {
            $mensagemErro = 'Preencha e-mail, CPF, telefone, WhatsApp e senha para criar a conta.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagemErro = 'Informe um e-mail valido.';
        } elseif (strlen($cpf) !== 11) {
            $mensagemErro = 'CPF invalido. Digite os 11 numeros do CPF.';
        } elseif (!telefoneComCodigoPaisValido($telefone) || !telefoneComCodigoPaisValido($whatsapp)) {
            $mensagemErro = 'Telefone e WhatsApp devem comecar com 55 e conter DDD + numero valido (12 ou 13 digitos).';
        } else {
            $stmtExiste = $conn->prepare('SELECT id FROM usuarios_login WHERE email = :email OR cpf = :cpf LIMIT 1');
            $stmtExiste->bindValue(':email', $email);
            $stmtExiste->bindValue(':cpf', $cpf);
            $stmtExiste->execute();

            if ($stmtExiste->fetch() !== false) {
                $mensagemErro = 'Nao e permitido criar conta com e-mail ou CPF ja cadastrados.';
            } else {
                $stmtInsert = $conn->prepare(
                    'INSERT INTO usuarios_login (email, cpf, telefone, whatsapp, senha_hash)
                     VALUES (:email, :cpf, :telefone, :whatsapp, :senha_hash)'
                );
                $stmtInsert->bindValue(':email', $email);
                $stmtInsert->bindValue(':cpf', $cpf);
                $stmtInsert->bindValue(':telefone', $telefone);
                $stmtInsert->bindValue(':whatsapp', $whatsapp);
                $stmtInsert->bindValue(':senha_hash', password_hash($senha, PASSWORD_DEFAULT));
                $stmtInsert->execute();

                $mensagemSucesso = 'Conta criada com sucesso. Agora faca login.';
            }
        }
    }

    if ($acao === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $cpf = normalizarCpf((string) ($_POST['cpf'] ?? ''));
        $telefone = normalizarTelefone((string) ($_POST['telefone'] ?? ''));
        $whatsapp = normalizarTelefone((string) ($_POST['whatsapp'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');

        if ($email === '' || $cpf === '' || $telefone === '' || $whatsapp === '' || $senha === '') {
            $mensagemErro = 'Para entrar, informe e-mail, CPF, telefone, WhatsApp e senha.';
        } elseif (!telefoneComCodigoPaisValido($telefone) || !telefoneComCodigoPaisValido($whatsapp)) {
            $mensagemErro = 'Telefone e WhatsApp devem comecar com 55 e conter DDD + numero valido (12 ou 13 digitos).';
        } else {
            $stmtUsuario = $conn->prepare(
                'SELECT id, email, cpf, telefone, whatsapp, senha_hash
                 FROM usuarios_login
                 WHERE email = :email
                   AND cpf = :cpf
                   AND telefone = :telefone
                   AND whatsapp = :whatsapp
                 LIMIT 1'
            );
            $stmtUsuario->bindValue(':email', $email);
            $stmtUsuario->bindValue(':cpf', $cpf);
            $stmtUsuario->bindValue(':telefone', $telefone);
            $stmtUsuario->bindValue(':whatsapp', $whatsapp);
            $stmtUsuario->execute();

            $usuario = $stmtUsuario->fetch();
            if ($usuario === false || !password_verify($senha, (string) $usuario['senha_hash'])) {
                $mensagemErro = 'Login invalido. Verifique os dados.';
            } else {
                $_SESSION['usuario'] = [
                    'id' => (int) $usuario['id'],
                    'email' => (string) $usuario['email'],
                    'cpf' => (string) $usuario['cpf'],
                    'telefone' => (string) $usuario['telefone'],
                    'whatsapp' => (string) $usuario['whatsapp'],
                ];
                $mensagemSucesso = 'Login realizado com sucesso.';
            }
        }
    }

    if ($acao === 'add_item') {
        if (!isset($_SESSION['usuario'])) {
            $mensagemErro = 'Faca login para adicionar itens.';
        } else {
            $nomeItem = trim((string) ($_POST['item_nome'] ?? ''));
            $quantidadeTexto = trim((string) ($_POST['item_quantidade'] ?? '1'));
            $preco = normalizarPreco((string) ($_POST['item_preco'] ?? ''));
            $quantidade = (int) $quantidadeTexto;

            if ($nomeItem === '' || $preco === null || $quantidade < 1 || (string) $quantidade !== $quantidadeTexto) {
                $mensagemErro = 'Informe item, quantidade valida e preco valido.';
            } else {
                $_SESSION['carrinho_itens'][] = [
                    'nome' => $nomeItem,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco,
                ];
                $mensagemSucesso = 'Item adicionado ao carrinho.';
                $itemNomeForm = '';
                $itemQuantidadeForm = '1';
                $itemPrecoForm = '';
            }
        }
    }

    if ($acao === 'limpar_carrinho') {
        $_SESSION['carrinho_itens'] = [];
        $mensagemSucesso = 'Carrinho limpo.';
    }

    if ($acao === 'finalizar_pedido') {
        if (!isset($_SESSION['usuario'])) {
            $mensagemErro = 'Faca login antes de finalizar o pedido.';
        } elseif (count($_SESSION['carrinho_itens']) === 0) {
            $mensagemErro = 'Seu carrinho esta vazio.';
        } else {
            $endereco = trim((string) ($_POST['endereco_entrega'] ?? ''));
            $cidade = trim((string) ($_POST['cidade'] ?? ''));
            $cep = trim((string) ($_POST['cep'] ?? ''));
            $obs = trim((string) ($_POST['observacoes'] ?? ''));

            if ($endereco === '' || $cidade === '' || $cep === '') {
                $mensagemErro = 'Preencha endereco, cidade e CEP da entrega.';
            } else {
                $itensCarrinho = $_SESSION['carrinho_itens'];
                $total = 0.0;
                foreach ($itensCarrinho as $item) {
                    $total += ((float) $item['preco_unitario']) * ((int) $item['quantidade']);
                }

                try {
                    $conn->beginTransaction();

                    $stmtPedido = $conn->prepare(
                        'INSERT INTO pedidos_login (usuario_id, endereco_entrega, cidade, cep, observacoes, total)
                         VALUES (:usuario_id, :endereco_entrega, :cidade, :cep, :observacoes, :total)'
                    );
                    $stmtPedido->bindValue(':usuario_id', (int) $_SESSION['usuario']['id'], PDO::PARAM_INT);
                    $stmtPedido->bindValue(':endereco_entrega', $endereco);
                    $stmtPedido->bindValue(':cidade', $cidade);
                    $stmtPedido->bindValue(':cep', $cep);
                    $stmtPedido->bindValue(':observacoes', $obs);
                    $stmtPedido->bindValue(':total', $total);
                    $stmtPedido->execute();

                    $pedidoId = (int) $conn->lastInsertId();

                    $stmtItem = $conn->prepare(
                        'INSERT INTO pedido_itens_login (pedido_id, nome_item, quantidade, preco_unitario, subtotal)
                         VALUES (:pedido_id, :nome_item, :quantidade, :preco_unitario, :subtotal)'
                    );

                    foreach ($itensCarrinho as $item) {
                        $subtotal = ((float) $item['preco_unitario']) * ((int) $item['quantidade']);
                        $stmtItem->bindValue(':pedido_id', $pedidoId, PDO::PARAM_INT);
                        $stmtItem->bindValue(':nome_item', (string) $item['nome']);
                        $stmtItem->bindValue(':quantidade', (int) $item['quantidade'], PDO::PARAM_INT);
                        $stmtItem->bindValue(':preco_unitario', (float) $item['preco_unitario']);
                        $stmtItem->bindValue(':subtotal', $subtotal);
                        $stmtItem->execute();
                    }

                    $conn->commit();
                    $_SESSION['carrinho_itens'] = [];
                    $mensagemSucesso = 'Pedido finalizado com sucesso.';
                    $enderecoForm = '';
                    $cidadeForm = '';
                    $cepForm = '';
                    $obsForm = '';
                } catch (Throwable $exception) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log('Erro ao finalizar pedido: ' . $exception->getMessage());
                    $mensagemErro = 'Nao foi possivel finalizar o pedido.';
                }
            }
        }
    }
}

$usuarioLogado = $_SESSION['usuario'] ?? null;
$itensCarrinho = $_SESSION['carrinho_itens'];
$totalCarrinho = 0.0;
foreach ($itensCarrinho as $item) {
    $totalCarrinho += ((float) $item['preco_unitario']) * ((int) $item['quantidade']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login e Pedidos</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="container">
        <header class="hero">
            <p class="selo">Loja</p>
            <h1>Login, Pedido e Carrinho</h1>
            <p class="subtitulo">Cadastre ou entre com e-mail, CPF e senha para fazer seu pedido.</p>
        </header>

        <?php if ($mensagemErro !== ''): ?>
            <p class="msg erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if ($mensagemSucesso !== ''): ?>
            <p class="msg sucesso"><?php echo htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (!is_array($usuarioLogado)): ?>
            <section class="grid-auth">
                <article class="card">
                    <h2>Criar conta</h2>
                    <form method="post" action="" class="form-coluna">
                        <input type="hidden" name="acao" value="cadastro">
                        <label class="campo">
                            <span>E-mail</span>
                            <input type="email" name="email"
                                value="<?php echo htmlspecialchars($emailForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="campo">
                            <span>CPF</span>
                            <input type="text" name="cpf"
                                value="<?php echo htmlspecialchars($cpfForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="campo">
                            <span>Telefone</span>
                            <input type="text" name="telefone"
                                value="<?php echo htmlspecialchars($telefoneForm, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Ex.: 5511999999999" pattern="55[0-9]{10,11}" maxlength="13" required>
                        </label>
                        <label class="campo">
                            <span>WhatsApp</span>
                            <input type="text" name="whatsapp"
                                value="<?php echo htmlspecialchars($whatsappForm, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Ex.: 5511999999999" pattern="55[0-9]{10,11}" maxlength="13" required>
                        </label>
                        <label class="campo">
                            <span>Senha</span>
                            <input type="password" name="senha" required>
                        </label>
                        <button type="submit" class="botao">Criar conta</button>
                    </form>
                </article>

                <article class="card">
                    <h2>Login</h2>
                    <form method="post" action="" class="form-coluna">
                        <input type="hidden" name="acao" value="login">
                        <label class="campo">
                            <span>E-mail</span>
                            <input type="email" name="email"
                                value="<?php echo htmlspecialchars($emailForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="campo">
                            <span>CPF</span>
                            <input type="text" name="cpf"
                                value="<?php echo htmlspecialchars($cpfForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="campo">
                            <span>Telefone</span>
                            <input type="text" name="telefone"
                                value="<?php echo htmlspecialchars($telefoneForm, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Ex.: 5511999999999" pattern="55[0-9]{10,11}" maxlength="13" required>
                        </label>
                        <label class="campo">
                            <span>WhatsApp</span>
                            <input type="text" name="whatsapp"
                                value="<?php echo htmlspecialchars($whatsappForm, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Ex.: 5511999999999" pattern="55[0-9]{10,11}" maxlength="13" required>
                        </label>
                        <label class="campo">
                            <span>Senha</span>
                            <input type="password" name="senha" required>
                        </label>
                        <button type="submit" class="botao botao-secundario">Entrar</button>
                    </form>
                </article>
            </section>
        <?php else: ?>
            <section class="card usuario-card">
                <div>
                    <h2>Usuario logado</h2>
                    <p><strong>E-mail:</strong>
                        <?php echo htmlspecialchars((string) $usuarioLogado['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>CPF:</strong>
                        <?php echo htmlspecialchars((string) $usuarioLogado['cpf'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Telefone:</strong>
                        <?php echo htmlspecialchars((string) $usuarioLogado['telefone'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>WhatsApp:</strong>
                        <?php echo htmlspecialchars((string) $usuarioLogado['whatsapp'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="acao" value="logout">
                    <button type="submit" class="botao perigo">Sair</button>
                </form>
            </section>

            <section class="card">
                <h2>Pedido</h2>
                <form method="post" action="" class="form-grid">
                    <input type="hidden" name="acao" value="add_item">

                    <label class="campo campo-largo">
                        <span>O que quer comprar</span>
                        <input type="text" name="item_nome"
                            value="<?php echo htmlspecialchars($itemNomeForm, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: Mouse Gamer" required>
                    </label>

                    <label class="campo">
                        <span>Quantidade</span>
                        <input type="number" name="item_quantidade" min="1" step="1"
                            value="<?php echo htmlspecialchars($itemQuantidadeForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>

                    <label class="campo">
                        <span>Preco (R$)</span>
                        <input type="text" name="item_preco"
                            value="<?php echo htmlspecialchars($itemPrecoForm, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: 120,00" required>
                    </label>

                    <button type="submit" class="botao">Adicionar ao carrinho</button>
                </form>
            </section>

            <section class="card">
                <h2>Carrinho e entrega</h2>
                <?php if (count($itensCarrinho) === 0): ?>
                    <p class="aviso">Ainda nao ha itens no carrinho.</p>
                <?php else: ?>
                    <div class="tabela-responsiva">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantidade</th>
                                    <th>Preco</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itensCarrinho as $item): ?>
                                    <?php $subtotal = ((float) $item['preco_unitario']) * ((int) $item['quantidade']); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $item['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $item['quantidade']; ?></td>
                                        <td>R$ <?php echo number_format((float) $item['preco_unitario'], 2, ',', '.'); ?></td>
                                        <td>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="total">Total: <strong>R$ <?php echo number_format($totalCarrinho, 2, ',', '.'); ?></strong></p>
                <?php endif; ?>

                <form method="post" action="" class="form-grid entrega-grid">
                    <input type="hidden" name="acao" value="finalizar_pedido">
                    <label class="campo campo-largo">
                        <span>Endereco de entrega</span>
                        <input type="text" name="endereco_entrega"
                            value="<?php echo htmlspecialchars($enderecoForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="campo">
                        <span>Cidade</span>
                        <input type="text" name="cidade"
                            value="<?php echo htmlspecialchars($cidadeForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </label>
                    <label class="campo">
                        <span>CEP</span>
                        <input type="text" name="cep" value="<?php echo htmlspecialchars($cepForm, ENT_QUOTES, 'UTF-8'); ?>"
                            required>
                    </label>
                    <label class="campo campo-largo">
                        <span>Observacoes (opcional)</span>
                        <input type="text" name="observacoes"
                            value="<?php echo htmlspecialchars($obsForm, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ex.: entregar apos 18h">
                    </label>
                    <button type="submit" class="botao botao-secundario">Finalizar pedido</button>
                </form>

                <form method="post" action="" class="form-limpar">
                    <input type="hidden" name="acao" value="limpar_carrinho">
                    <button type="submit" class="botao perigo">Limpar carrinho</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>