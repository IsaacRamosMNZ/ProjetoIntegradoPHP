<?php

declare(strict_types=1);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$destino = 'gestao_clientes.php';

if ($id > 0) {
    $destino .= '?editar_id=' . $id;
}

header('Location: ' . $destino);
exit;
