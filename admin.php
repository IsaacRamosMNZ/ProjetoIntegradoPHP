<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/Database.php';

const SENHA_ADMIN = '040221219';

function limparNumero(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

$mensagemErro = '';
$mensagemSucesso = '';
$editandoPedidoId = 0;

$database = new Database();
$conn = $database->getConnection();
$database->ensureSchema($conn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'admin_login') {
        $senha = (string) ($_POST['senha_admin'] ?? '');
        if ($senha === SENHA_ADMIN) {
            $_SESSION['admin_autenticado'] = true;
            $mensagemSucesso = 'Acesso liberado no painel admin.';
        } else {
            $mensagemErro = 'Senha admin incorreta.';
        }
    }

    if ($acao === 'admin_logout') {
        unset($_SESSION['admin_autenticado']);
        header('Location: admin.php');
        exit;
    }

    if (!empty($_SESSION['admin_autenticado']) && $acao !== 'admin_login' && $acao !== 'admin_logout') {
        if ($acao === 'ocultar_pedido') {
            $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE pedidos_login SET oculto_admin = 1 WHERE id = :id');
            $stmt->bindValue(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();
            $mensagemSucesso = 'Pedido ocultado no painel.';
        }

        if ($acao === 'mostrar_pedido') {
            $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE pedidos_login SET oculto_admin = 0 WHERE id = :id');
            $stmt->bindValue(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();
            $mensagemSucesso = 'Pedido exibido novamente no painel.';
        }

        if ($acao === 'apagar_pedido') {
            $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM pedidos_login WHERE id = :id');
            $stmt->bindValue(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();
            $mensagemSucesso = 'Pedido apagado com sucesso.';
        }

        if ($acao === 'editar_pedido') {
            $editandoPedidoId = (int) ($_POST['pedido_id'] ?? 0);
        }

        if ($acao === 'salvar_edicao_pedido') {
            $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
            $endereco = trim((string) ($_POST['endereco_entrega'] ?? ''));
            $cidade = trim((string) ($_POST['cidade'] ?? ''));
            $cep = trim((string) ($_POST['cep'] ?? ''));
            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

            if ($pedidoId < 1 || $endereco === '' || $cidade === '' || $cep === '') {
                $mensagemErro = 'Para editar, informe endereco, cidade e CEP validos.';
                $editandoPedidoId = $pedidoId;
            } else {
                $stmt = $conn->prepare(
                    'UPDATE pedidos_login
                     SET endereco_entrega = :endereco, cidade = :cidade, cep = :cep, observacoes = :observacoes
                     WHERE id = :id'
                );
                $stmt->bindValue(':id', $pedidoId, PDO::PARAM_INT);
                $stmt->bindValue(':endereco', $endereco);
                $stmt->bindValue(':cidade', $cidade);
                $stmt->bindValue(':cep', $cep);
                $stmt->bindValue(':observacoes', $observacoes);
                $stmt->execute();

                $mensagemSucesso = 'Pedido editado com sucesso.';
            }
        }
    }
}

$adminAutenticado = !empty($_SESSION['admin_autenticado']);
$mostrarOcultos = (string) ($_GET['ocultos'] ?? '0') === '1';

$pedidosComItens = [];
if ($adminAutenticado) {
    $filtroOcultosSql = $mostrarOcultos ? '' : ' AND p.oculto_admin = 0';

    $stmtPedidos = $conn->query(
        'SELECT p.id, p.endereco_entrega, p.cidade, p.cep, p.observacoes, p.total, p.criado_em, p.oculto_admin,
                u.email AS cliente_email, u.cpf AS cliente_cpf, u.telefone AS cliente_telefone, u.whatsapp AS cliente_whatsapp
         FROM pedidos_login p
         INNER JOIN usuarios_login u ON u.id = p.usuario_id
         WHERE 1 = 1' . $filtroOcultosSql . '
         ORDER BY p.id DESC'
    );
    $pedidos = $stmtPedidos->fetchAll();

    if (is_array($pedidos) && count($pedidos) > 0) {
        $ids = [];
        foreach ($pedidos as $pedido) {
            $ids[] = (int) $pedido['id'];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtItens = $conn->prepare(
            'SELECT pedido_id, nome_item, quantidade, preco_unitario, subtotal
             FROM pedido_itens_login
             WHERE pedido_id IN (' . $placeholders . ')
             ORDER BY id ASC'
        );

        foreach ($ids as $index => $pedidoId) {
            $stmtItens->bindValue($index + 1, $pedidoId, PDO::PARAM_INT);
        }

        $stmtItens->execute();
        $linhasItens = $stmtItens->fetchAll();

        $itensPorPedido = [];
        if (is_array($linhasItens)) {
            foreach ($linhasItens as $linha) {
                $pedidoId = (int) $linha['pedido_id'];
                if (!isset($itensPorPedido[$pedidoId])) {
                    $itensPorPedido[$pedidoId] = [];
                }
                $itensPorPedido[$pedidoId][] = $linha;
            }
        }

        foreach ($pedidos as $pedido) {
            $pedidoId = (int) $pedido['id'];
            $pedido['itens'] = $itensPorPedido[$pedidoId] ?? [];
            $pedidosComItens[] = $pedido;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pedidos</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="container">
        <header class="hero">
            <p class="selo">Painel Admin</p>
            <h1>Administracao de Pedidos</h1>
            <p class="subtitulo">Edite, contate cliente no WhatsApp, oculte e apague pedidos.</p>
        </header>

        <?php if ($mensagemErro !== ''): ?>
            <p class="msg erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if ($mensagemSucesso !== ''): ?>
            <p class="msg sucesso"><?php echo htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (!$adminAutenticado): ?>
            <section class="card">
                <h2>Entrar no Admin</h2>
                <form method="post" action="" class="form-coluna">
                    <input type="hidden" name="acao" value="admin_login">
                    <label class="campo">
                        <span>Senha do admin</span>
                        <input type="password" name="senha_admin" required>
                    </label>
                    <button type="submit" class="botao">Entrar</button>
                </form>
            </section>
        <?php else: ?>
            <section class="card usuario-card">
                <div>
                    <h2>Painel liberado</h2>
                    <p>Use as acoes abaixo para gerenciar pedidos.</p>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="acao" value="admin_logout">
                    <button type="submit" class="botao perigo">Sair do admin</button>
                </form>
            </section>

            <section class="card painel-pedidos">
                <div class="top-menu">
                    <a class="top-menu-link <?php echo !$mostrarOcultos ? 'ativo' : ''; ?>" href="admin.php">Somente
                        visiveis</a>
                    <a class="top-menu-link <?php echo $mostrarOcultos ? 'ativo' : ''; ?>"
                        href="admin.php?ocultos=1">Incluir ocultos</a>
                    <a class="top-menu-link" href="gestao_clientes.php">Gestao de clientes</a>
                    <a class="top-menu-link" href="index.php">Ir para loja</a>
                </div>

                <?php if (count($pedidosComItens) === 0): ?>
                    <p class="aviso">Nenhum pedido encontrado.</p>
                <?php else: ?>
                    <div class="lista-pedidos">
                        <?php foreach ($pedidosComItens as $pedido): ?>
                            <?php $pedidoId = (int) $pedido['id']; ?>
                            <article class="pedido-item">
                                <header class="pedido-topo">
                                    <h3>Pedido #<?php echo $pedidoId; ?></h3>
                                    <p class="pedido-data">
                                        <?php echo htmlspecialchars((string) $pedido['criado_em'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </header>

                                <p><strong>Cliente:</strong>
                                    <?php echo htmlspecialchars((string) $pedido['cliente_email'], ENT_QUOTES, 'UTF-8'); ?>
                                    | CPF: <?php echo htmlspecialchars((string) $pedido['cliente_cpf'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>Telefone:</strong>
                                    <?php echo htmlspecialchars((string) $pedido['cliente_telefone'], ENT_QUOTES, 'UTF-8'); ?>
                                    | <strong>WhatsApp:</strong>
                                    <?php echo htmlspecialchars((string) $pedido['cliente_whatsapp'], ENT_QUOTES, 'UTF-8'); ?></p>

                                <p><strong>Entrega:</strong>
                                    <?php echo htmlspecialchars((string) $pedido['endereco_entrega'], ENT_QUOTES, 'UTF-8'); ?>,
                                    <?php echo htmlspecialchars((string) $pedido['cidade'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo htmlspecialchars((string) $pedido['cep'], ENT_QUOTES, 'UTF-8'); ?>)
                                </p>

                                <p><strong>Observacao:</strong>
                                    <?php echo htmlspecialchars((string) ($pedido['observacoes'] !== '' ? $pedido['observacoes'] : 'Sem observacoes.'), ENT_QUOTES, 'UTF-8'); ?>
                                </p>

                                <div class="tabela-responsiva pedido-tabela">
                                    <table class="tabela-compras">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Qtd</th>
                                                <th>Preco</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pedido['itens'] as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string) $item['nome_item'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </td>
                                                    <td><?php echo (int) $item['quantidade']; ?></td>
                                                    <td>R$ <?php echo number_format((float) $item['preco_unitario'], 2, ',', '.'); ?>
                                                    </td>
                                                    <td>R$ <?php echo number_format((float) $item['subtotal'], 2, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <p class="pedido-total"><strong>Total: R$
                                        <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?></strong></p>

                                <div class="admin-acoes">
                                    <form method="post" action="">
                                        <input type="hidden" name="acao" value="editar_pedido">
                                        <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">
                                        <button type="submit" class="botao">Editar pedido</button>
                                    </form>

                                    <a class="botao admin-link"
                                        href="https://wa.me/<?php echo urlencode(limparNumero((string) $pedido['cliente_whatsapp'])); ?>?text=<?php echo urlencode('Ola! Sobre o seu pedido #' . $pedidoId); ?>"
                                        target="_blank" rel="noopener noreferrer">Entrar em contato no WhatsApp</a>

                                    <?php if ((int) $pedido['oculto_admin'] === 0): ?>
                                        <form method="post" action="">
                                            <input type="hidden" name="acao" value="ocultar_pedido">
                                            <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">
                                            <button type="submit" class="botao botao-secundario">Ocultar pedido</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="">
                                            <input type="hidden" name="acao" value="mostrar_pedido">
                                            <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">
                                            <button type="submit" class="botao botao-secundario">Mostrar pedido</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="" onsubmit="return confirm('Deseja apagar este pedido?');">
                                        <input type="hidden" name="acao" value="apagar_pedido">
                                        <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">
                                        <button type="submit" class="botao perigo">Apagar pedido</button>
                                    </form>
                                </div>

                                <?php if ($editandoPedidoId === $pedidoId): ?>
                                    <form method="post" action="" class="form-grid admin-edicao">
                                        <input type="hidden" name="acao" value="salvar_edicao_pedido">
                                        <input type="hidden" name="pedido_id" value="<?php echo $pedidoId; ?>">

                                        <label class="campo campo-largo">
                                            <span>Endereco</span>
                                            <input type="text" name="endereco_entrega"
                                                value="<?php echo htmlspecialchars((string) $pedido['endereco_entrega'], ENT_QUOTES, 'UTF-8'); ?>"
                                                required>
                                        </label>

                                        <label class="campo">
                                            <span>Cidade</span>
                                            <input type="text" name="cidade"
                                                value="<?php echo htmlspecialchars((string) $pedido['cidade'], ENT_QUOTES, 'UTF-8'); ?>"
                                                required>
                                        </label>

                                        <label class="campo">
                                            <span>CEP</span>
                                            <input type="text" name="cep"
                                                value="<?php echo htmlspecialchars((string) $pedido['cep'], ENT_QUOTES, 'UTF-8'); ?>"
                                                required>
                                        </label>

                                        <label class="campo campo-largo">
                                            <span>Observacoes</span>
                                            <input type="text" name="observacoes"
                                                value="<?php echo htmlspecialchars((string) $pedido['observacoes'], ENT_QUOTES, 'UTF-8'); ?>">
                                        </label>

                                        <button type="submit" class="botao">Salvar edicao</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>