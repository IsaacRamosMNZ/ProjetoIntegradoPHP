<?php

declare(strict_types=1);

require_once __DIR__ . '/dao/UsuarioLoginDAO.php';

function normalizarNumeros(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function cpfValido(string $cpf): bool
{
    return strlen($cpf) === 11;
}

function telefoneComCodigoPaisValido(string $telefone): bool
{
    return (bool) preg_match('/^55\d{10,11}$/', $telefone);
}

$mensagem = '';
$tipoMensagem = '';

$usuarioDAO = null;
try {
    $usuarioDAO = new UsuarioLoginDAO();
} catch (Throwable $exception) {
    error_log('Falha ao carregar gestao de usuarios: ' . $exception->getMessage());
    $mensagem = 'Nao foi possivel conectar ao banco de dados da gestao de usuarios.';
    $tipoMensagem = 'erro';
}

$emailForm = '';
$cpfForm = '';
$telefoneForm = '';
$whatsappForm = '';
$senhaForm = '';

$usuarioEdicao = null;
$buscaUsuario = null;
$buscaIdTexto = trim((string) ($_GET['buscar_id'] ?? ''));

$msgCodigo = (string) ($_GET['msg'] ?? '');
if ($msgCodigo === 'cadastrado') {
    $mensagem = 'Usuario cadastrado com sucesso.';
    $tipoMensagem = 'sucesso';
} elseif ($msgCodigo === 'atualizado') {
    $mensagem = 'Usuario atualizado com sucesso.';
    $tipoMensagem = 'sucesso';
} elseif ($msgCodigo === 'excluido') {
    $mensagem = 'Usuario excluido com sucesso.';
    $tipoMensagem = 'sucesso';
} elseif ($msgCodigo === 'erro') {
    $mensagem = 'Nao foi possivel concluir a operacao.';
    $tipoMensagem = 'erro';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $usuarioDAO instanceof UsuarioLoginDAO) {
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'cadastrar') {
        $emailForm = trim((string) ($_POST['email'] ?? ''));
        $cpfForm = normalizarNumeros((string) ($_POST['cpf'] ?? ''));
        $telefoneForm = normalizarNumeros((string) ($_POST['telefone'] ?? ''));
        $whatsappForm = normalizarNumeros((string) ($_POST['whatsapp'] ?? ''));
        $senhaForm = (string) ($_POST['senha'] ?? '');

        if ($emailForm === '' || $cpfForm === '' || $telefoneForm === '' || $whatsappForm === '' || $senhaForm === '') {
            $mensagem = 'Preencha todos os campos obrigatorios para cadastro.';
            $tipoMensagem = 'erro';
        } elseif (!filter_var($emailForm, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Informe um e-mail valido.';
            $tipoMensagem = 'erro';
        } elseif (!cpfValido($cpfForm)) {
            $mensagem = 'CPF invalido. Informe 11 digitos.';
            $tipoMensagem = 'erro';
        } elseif (!telefoneComCodigoPaisValido($telefoneForm) || !telefoneComCodigoPaisValido($whatsappForm)) {
            $mensagem = 'Telefone e WhatsApp devem comecar com 55 e conter DDD + numero valido.';
            $tipoMensagem = 'erro';
        } else {
            try {
                if ($usuarioDAO->inserir($emailForm, $cpfForm, $telefoneForm, $whatsappForm, $senhaForm)) {
                    header('Location: gestao_clientes.php?msg=cadastrado');
                    exit;
                }
                $mensagem = 'Erro ao cadastrar usuario.';
                $tipoMensagem = 'erro';
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $mensagem = 'E-mail ou CPF ja cadastrado.';
                } else {
                    $mensagem = 'Erro ao cadastrar usuario.';
                }
                $tipoMensagem = 'erro';
            }
        }
    }

    if ($acao === 'atualizar') {
        $id = (int) ($_POST['id'] ?? 0);
        $emailForm = trim((string) ($_POST['email'] ?? ''));
        $cpfForm = normalizarNumeros((string) ($_POST['cpf'] ?? ''));
        $telefoneForm = normalizarNumeros((string) ($_POST['telefone'] ?? ''));
        $whatsappForm = normalizarNumeros((string) ($_POST['whatsapp'] ?? ''));
        $senhaForm = (string) ($_POST['senha'] ?? '');

        $usuarioEdicao = $usuarioDAO->buscarPorId($id);

        if ($usuarioEdicao === null) {
            $mensagem = 'Usuario nao encontrado para atualizacao.';
            $tipoMensagem = 'erro';
        } elseif (!filter_var($emailForm, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Informe um e-mail valido.';
            $tipoMensagem = 'erro';
        } elseif (!cpfValido($cpfForm)) {
            $mensagem = 'CPF invalido. Informe 11 digitos.';
            $tipoMensagem = 'erro';
        } elseif (!telefoneComCodigoPaisValido($telefoneForm) || !telefoneComCodigoPaisValido($whatsappForm)) {
            $mensagem = 'Telefone e WhatsApp devem comecar com 55 e conter DDD + numero valido.';
            $tipoMensagem = 'erro';
        } else {
            try {
                if ($usuarioDAO->atualizar($id, $emailForm, $cpfForm, $telefoneForm, $whatsappForm, $senhaForm)) {
                    header('Location: gestao_clientes.php?msg=atualizado');
                    exit;
                }
                $mensagem = 'Erro ao atualizar usuario.';
                $tipoMensagem = 'erro';
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $mensagem = 'E-mail ou CPF ja cadastrado para outro usuario.';
                } else {
                    $mensagem = 'Erro ao atualizar usuario.';
                }
                $tipoMensagem = 'erro';
            }
        }
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id < 1) {
            $mensagem = 'ID invalido para exclusao.';
            $tipoMensagem = 'erro';
        } else {
            try {
                if ($usuarioDAO->excluir($id)) {
                    header('Location: gestao_clientes.php?msg=excluido');
                    exit;
                }
                $mensagem = 'Usuario nao encontrado para exclusao.';
                $tipoMensagem = 'erro';
            } catch (Throwable $exception) {
                error_log('Falha ao excluir usuario: ' . $exception->getMessage());
                $mensagem = 'Erro ao excluir usuario e pedidos vinculados.';
                $tipoMensagem = 'erro';
            }
        }
    }
}

if ($usuarioEdicao === null && $usuarioDAO instanceof UsuarioLoginDAO) {
    $editarId = (int) ($_GET['editar_id'] ?? 0);
    if ($editarId > 0) {
        $usuarioEdicao = $usuarioDAO->buscarPorId($editarId);
        if ($usuarioEdicao === null) {
            $mensagem = 'Usuario nao encontrado para edicao.';
            $tipoMensagem = 'erro';
        } else {
            $emailForm = (string) $usuarioEdicao['email'];
            $cpfForm = (string) $usuarioEdicao['cpf'];
            $telefoneForm = (string) $usuarioEdicao['telefone'];
            $whatsappForm = (string) $usuarioEdicao['whatsapp'];
        }
    }
}

if ($buscaIdTexto !== '' && $usuarioDAO instanceof UsuarioLoginDAO) {
    if (!ctype_digit($buscaIdTexto) || (int) $buscaIdTexto < 1) {
        $mensagem = 'Informe um ID valido para busca.';
        $tipoMensagem = 'erro';
    } else {
        $buscaId = (int) $buscaIdTexto;
        $buscaUsuario = $usuarioDAO->buscarPorId($buscaId);
        if ($buscaUsuario === null) {
            $mensagem = 'Nenhum usuario encontrado para o ID informado.';
            $tipoMensagem = 'erro';
        }
    }
}

$usuarios = $usuarioDAO instanceof UsuarioLoginDAO ? $usuarioDAO->listar() : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestao de Usuarios</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main class="container">
        <header class="hero">
            <p class="selo">CRUD Completo com PDO</p>
            <h1>Gestao de Usuarios</h1>
            <p class="subtitulo">CRUD completo dos usuarios de login em uma unica pagina.</p>
        </header>

        <nav class="top-menu" aria-label="Navegacao principal">
            <a class="top-menu-link ativo" href="gestao_clientes.php">Gestao de Usuarios</a>
            <a class="top-menu-link" href="Compras.php">Compras</a>
            <a class="top-menu-link" href="admin.php">Admin</a>
            <a class="top-menu-link" href="index.php">Loja</a>
        </nav>

        <?php if ($mensagem !== ''): ?>
            <p class="msg <?php echo $tipoMensagem === 'erro' ? 'erro' : 'sucesso'; ?>">
                <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <section class="card form-card">
            <h2><?php echo is_array($usuarioEdicao) ? 'Atualizar Usuario' : 'Cadastrar Usuario'; ?></h2>
            <form method="post" action="" class="form-grid">
                <input type="hidden" name="acao"
                    value="<?php echo is_array($usuarioEdicao) ? 'atualizar' : 'cadastrar'; ?>">
                <?php if (is_array($usuarioEdicao)): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $usuarioEdicao['id']; ?>">
                <?php endif; ?>

                <label class="campo">
                    <span>E-mail</span>
                    <input type="email" name="email"
                        value="<?php echo htmlspecialchars($emailForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>

                <label class="campo">
                    <span>CPF</span>
                    <input type="text" name="cpf" maxlength="11"
                        value="<?php echo htmlspecialchars($cpfForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>

                <label class="campo">
                    <span>Telefone</span>
                    <input type="text" name="telefone" maxlength="13" pattern="55[0-9]{10,11}"
                        placeholder="Ex.: 5511999999999"
                        value="<?php echo htmlspecialchars($telefoneForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>

                <label class="campo">
                    <span>WhatsApp</span>
                    <input type="text" name="whatsapp" maxlength="13" pattern="55[0-9]{10,11}"
                        placeholder="Ex.: 5511999999999"
                        value="<?php echo htmlspecialchars($whatsappForm, ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>

                <label class="campo campo-largo">
                    <span><?php echo is_array($usuarioEdicao) ? 'Nova senha (opcional)' : 'Senha'; ?></span>
                    <input type="password" name="senha" <?php echo is_array($usuarioEdicao) ? '' : 'required'; ?>>
                </label>

                <button type="submit"
                    class="botao"><?php echo is_array($usuarioEdicao) ? 'Atualizar' : 'Salvar'; ?></button>
                <?php if (is_array($usuarioEdicao)): ?>
                    <a class="menu-link" href="gestao_clientes.php">Cancelar edicao</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="card form-card">
            <h2>Buscar Usuario por ID</h2>
            <form method="get" action="" class="form-grid busca-grid">
                <label class="campo">
                    <span>ID</span>
                    <input type="number" min="1" name="buscar_id"
                        value="<?php echo htmlspecialchars($buscaIdTexto, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Ex.: 1">
                </label>
                <button type="submit" class="botao botao-secundario">Buscar</button>
            </form>

            <?php if (is_array($buscaUsuario)): ?>
                <div class="tabela-responsiva resultado-busca">
                    <table class="tabela-compras">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>E-mail</th>
                                <th>CPF</th>
                                <th>Telefone</th>
                                <th>WhatsApp</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo (int) $buscaUsuario['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $buscaUsuario['email'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) $buscaUsuario['cpf'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $buscaUsuario['telefone'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) $buscaUsuario['whatsapp'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="tabela-acoes">
                                    <a class="menu-link"
                                        href="gestao_clientes.php?editar_id=<?php echo (int) $buscaUsuario['id']; ?>">Editar</a>
                                    <form method="post" action="" class="form-inline"
                                        onsubmit="return confirm('Tem certeza que deseja excluir este usuario? Esta acao tambem remove pedidos vinculados.');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?php echo (int) $buscaUsuario['id']; ?>">
                                        <button type="submit" class="botao perigo botao-mini">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="card tabela-card">
            <h2>Usuarios Cadastrados</h2>

            <?php if (count($usuarios) === 0): ?>
                <p class="aviso">Nenhum usuario cadastrado.</p>
            <?php else: ?>
                <div class="tabela-responsiva">
                    <table class="tabela-compras">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>E-mail</th>
                                <th>CPF</th>
                                <th>Telefone</th>
                                <th>WhatsApp</th>
                                <th>Pedidos</th>
                                <th>Criado em</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo (int) $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $usuario['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $usuario['cpf'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $usuario['telefone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $usuario['whatsapp'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) $usuario['total_pedidos']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $usuario['criado_em'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="tabela-acoes">
                                        <a class="menu-link"
                                            href="gestao_clientes.php?editar_id=<?php echo (int) $usuario['id']; ?>">Editar</a>
                                        <form method="post" action="" class="form-inline"
                                            onsubmit="return confirm('Tem certeza que deseja excluir este usuario? Esta acao tambem remove pedidos vinculados.');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?php echo (int) $usuario['id']; ?>">
                                            <button type="submit" class="botao perigo botao-mini">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>