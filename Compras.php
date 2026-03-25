<?php

declare(strict_types=1);

require_once __DIR__ . '/config/Database.php';

$mensagemErro = '';
$filtrosPeriodo = [
    'hoje' => 'Pedidos de hoje',
    'ontem' => 'Pedidos de ontem',
    'semana_passada' => 'Pedidos da semana passada',
    'todos' => 'Todos os pedidos',
];

$periodoSelecionado = (string) ($_GET['periodo'] ?? 'hoje');
if (!isset($filtrosPeriodo[$periodoSelecionado])) {
    $periodoSelecionado = 'hoje';
}

$pedidosComItens = [];

try {
    $database = new Database();
    $conn = $database->getConnection();
    $database->ensureSchema($conn);

    $hoje = new DateTimeImmutable('today');
    $amanha = $hoje->modify('+1 day');
    $ontem = $hoje->modify('-1 day');
    $inicioSemanaAtual = $hoje->modify('monday this week');
    $inicioSemanaPassada = $inicioSemanaAtual->modify('-7 days');

    $filtroSql = '';
    $paramInicio = null;
    $paramFim = null;

    if ($periodoSelecionado === 'hoje') {
        $filtroSql = ' AND p.criado_em >= :inicio_periodo AND p.criado_em < :fim_periodo';
        $paramInicio = $hoje->format('Y-m-d H:i:s');
        $paramFim = $amanha->format('Y-m-d H:i:s');
    } elseif ($periodoSelecionado === 'ontem') {
        $filtroSql = ' AND p.criado_em >= :inicio_periodo AND p.criado_em < :fim_periodo';
        $paramInicio = $ontem->format('Y-m-d H:i:s');
        $paramFim = $hoje->format('Y-m-d H:i:s');
    } elseif ($periodoSelecionado === 'semana_passada') {
        $filtroSql = ' AND p.criado_em >= :inicio_periodo AND p.criado_em < :fim_periodo';
        $paramInicio = $inicioSemanaPassada->format('Y-m-d H:i:s');
        $paramFim = $inicioSemanaAtual->format('Y-m-d H:i:s');
    }

    $sqlPedidos =
        'SELECT p.id, p.endereco_entrega, p.cidade, p.cep, p.observacoes, p.total, p.criado_em,
                u.email AS cliente_email, u.cpf AS cliente_cpf
         FROM pedidos_login p
         INNER JOIN usuarios_login u ON u.id = p.usuario_id
         WHERE 1 = 1' . $filtroSql . '
         ORDER BY p.id DESC';

    $stmtPedidos = $conn->prepare($sqlPedidos);

    if ($paramInicio !== null && $paramFim !== null) {
        $stmtPedidos->bindValue(':inicio_periodo', $paramInicio);
        $stmtPedidos->bindValue(':fim_periodo', $paramFim);
    }

    $stmtPedidos->execute();
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

        foreach ($ids as $indice => $idPedido) {
            $stmtItens->bindValue($indice + 1, $idPedido, PDO::PARAM_INT);
        }

        $stmtItens->execute();
        $itensLinhas = $stmtItens->fetchAll();

        $itensPorPedido = [];
        if (is_array($itensLinhas)) {
            foreach ($itensLinhas as $linhaItem) {
                $pedidoId = (int) $linhaItem['pedido_id'];
                if (!isset($itensPorPedido[$pedidoId])) {
                    $itensPorPedido[$pedidoId] = [];
                }
                $itensPorPedido[$pedidoId][] = $linhaItem;
            }
        }

        foreach ($pedidos as $pedido) {
            $pedidoId = (int) $pedido['id'];
            $pedido['itens'] = $itensPorPedido[$pedidoId] ?? [];
            $pedidosComItens[] = $pedido;
        }
    }
} catch (Throwable $exception) {
    error_log('Falha ao carregar compras: ' . $exception->getMessage());
    $mensagemErro = 'Nao foi possivel carregar a area de compras.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="container">
        <header class="hero">
            <p class="selo">Painel Administrativo</p>
            <h1>Compras</h1>
            <p class="subtitulo">Visualize os pedidos com itens, data, observacoes e entrega.</p>
        </header>

        <nav class="top-menu" aria-label="Navegacao principal">
            <a class="top-menu-link ativo" href="Compras.php">Compras</a>
            <a class="top-menu-link" href="gestao_clientes.php">Clientes</a>
            <a class="top-menu-link" href="index.php">Novo Pedido</a>
        </nav>

        <section class="card painel-pedidos">
            <h2>Historico de Pedidos</h2>

            <nav class="menu-pedidos" aria-label="Filtro de pedidos por periodo">
                <?php foreach ($filtrosPeriodo as $valorFiltro => $textoFiltro): ?>
                    <a href="?periodo=<?php echo urlencode($valorFiltro); ?>"
                        class="menu-link <?php echo $periodoSelecionado === $valorFiltro ? 'ativo' : ''; ?>">
                        <?php echo htmlspecialchars($textoFiltro, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($mensagemErro !== ''): ?>
                <p class="erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php elseif (count($pedidosComItens) === 0): ?>
                <p class="aviso">Nenhum pedido encontrado neste periodo.</p>
            <?php else: ?>
                <div class="lista-pedidos">
                    <?php foreach ($pedidosComItens as $pedido): ?>
                        <article class="pedido-item">
                            <header class="pedido-topo">
                                <h3>Pedido #<?php echo (int) $pedido['id']; ?></h3>
                                <p class="pedido-data">
                                    <?php echo htmlspecialchars((string) $pedido['criado_em'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </header>

                            <p><strong>Cliente:</strong>
                                <?php echo htmlspecialchars((string) $pedido['cliente_email'], ENT_QUOTES, 'UTF-8'); ?>
                                | CPF: <?php echo htmlspecialchars((string) $pedido['cliente_cpf'], ENT_QUOTES, 'UTF-8'); ?></p>

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
                                            <th>Item pedido</th>
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

                            <p class="pedido-total"><strong>Total do pedido: R$
                                    <?php echo number_format((float) $pedido['total'], 2, ',', '.'); ?></strong></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>