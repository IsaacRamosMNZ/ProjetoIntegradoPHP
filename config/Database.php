<?php

declare(strict_types=1);

class Database
{
    private string $host;
    private string $database;
    private string $user;
    private string $password;

    public function __construct()
    {
        $config = $this->carregarConfiguracao();

        $this->host = (string) ($config['host'] ?? '127.0.0.1');
        $this->database = (string) ($config['database'] ?? 'loja');
        $this->user = (string) ($config['user'] ?? 'root');
        $this->password = (string) ($config['password'] ?? '');
    }

    public function getConnection(): PDO
    {
        $connServidor = new PDO(
            sprintf('mysql:host=%s;charset=utf8mb4', $this->host),
            $this->user,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $connServidor->exec(
            sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '', $this->database)
            )
        );

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $this->host, $this->database);
        return new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function ensureSchema(PDO $conn): void
    {
        $queries = [
            'CREATE TABLE IF NOT EXISTS clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
                endereco VARCHAR(220) NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS produtos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(180) NOT NULL,
                preco DECIMAL(10,2) NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_produto_nome_preco (nome, preco)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS pedidos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                numero INT UNSIGNED NOT NULL UNIQUE,
                cliente_id INT NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id)
                    REFERENCES clientes (id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS pedido_itens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT UNSIGNED NOT NULL,
                produto_id INT UNSIGNED NOT NULL,
                quantidade INT UNSIGNED NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                CONSTRAINT fk_itens_pedido FOREIGN KEY (pedido_id)
                    REFERENCES pedidos (id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id)
                    REFERENCES produtos (id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS usuarios_login (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(150) NOT NULL,
                cpf VARCHAR(14) NOT NULL,
                telefone VARCHAR(20) NOT NULL DEFAULT "",
                whatsapp VARCHAR(20) NOT NULL DEFAULT "",
                senha_hash VARCHAR(255) NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_usuarios_login_email (email),
                UNIQUE KEY uq_usuarios_login_cpf (cpf)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS pedidos_login (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT UNSIGNED NOT NULL,
                endereco_entrega VARCHAR(220) NOT NULL,
                cidade VARCHAR(120) NOT NULL,
                cep VARCHAR(12) NOT NULL,
                observacoes VARCHAR(255) NOT NULL DEFAULT "",
                oculto_admin TINYINT(1) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_pedidos_login_usuario FOREIGN KEY (usuario_id)
                    REFERENCES usuarios_login (id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS pedido_itens_login (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT UNSIGNED NOT NULL,
                nome_item VARCHAR(180) NOT NULL,
                quantidade INT UNSIGNED NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                CONSTRAINT fk_itens_login_pedido FOREIGN KEY (pedido_id)
                    REFERENCES pedidos_login (id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($queries as $query) {
            $conn->exec($query);
        }

        if (!$this->colunaExiste($conn, 'clientes', 'endereco')) {
            $conn->exec('ALTER TABLE clientes ADD COLUMN endereco VARCHAR(220) NOT NULL DEFAULT "" AFTER email');
        }

        if (!$this->colunaExiste($conn, 'clientes', 'criado_em')) {
            $conn->exec('ALTER TABLE clientes ADD COLUMN criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        }

        if (!$this->indiceExiste($conn, 'usuarios_login', 'uq_usuarios_login_email')) {
            $conn->exec('ALTER TABLE usuarios_login ADD UNIQUE KEY uq_usuarios_login_email (email)');
        }

        if (!$this->indiceExiste($conn, 'usuarios_login', 'uq_usuarios_login_cpf')) {
            $conn->exec('ALTER TABLE usuarios_login ADD UNIQUE KEY uq_usuarios_login_cpf (cpf)');
        }

        if (!$this->colunaExiste($conn, 'usuarios_login', 'telefone')) {
            $conn->exec('ALTER TABLE usuarios_login ADD COLUMN telefone VARCHAR(20) NOT NULL DEFAULT "" AFTER cpf');
        }

        if (!$this->colunaExiste($conn, 'usuarios_login', 'whatsapp')) {
            $conn->exec('ALTER TABLE usuarios_login ADD COLUMN whatsapp VARCHAR(20) NOT NULL DEFAULT "" AFTER telefone');
        }

        if (!$this->colunaExiste($conn, 'pedidos_login', 'oculto_admin')) {
            $conn->exec('ALTER TABLE pedidos_login ADD COLUMN oculto_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER observacoes');
        }

    }

    private function colunaExiste(PDO $conn, string $tabela, string $coluna): bool
    {
        $stmt = $conn->prepare(sprintf('SHOW COLUMNS FROM `%s` LIKE :coluna', str_replace('`', '', $tabela)));
        $stmt->bindValue(':coluna', $coluna);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }

    private function indiceExiste(PDO $conn, string $tabela, string $indice): bool
    {
        $stmt = $conn->prepare(sprintf('SHOW INDEX FROM `%s` WHERE Key_name = :indice', str_replace('`', '', $tabela)));
        $stmt->bindValue(':indice', $indice);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }

    private function carregarConfiguracao(): array
    {
        $arquivoConfig = __DIR__ . '/db.php';
        $configArquivo = [];

        if (is_file($arquivoConfig)) {
            $carregado = require $arquivoConfig;
            if (is_array($carregado)) {
                $configArquivo = $carregado;
            }
        }

        return [
            'host' => getenv('DB_HOST') ?: ($configArquivo['host'] ?? '127.0.0.1'),
            'database' => getenv('DB_NAME') ?: ($configArquivo['database'] ?? 'loja'),
            'user' => getenv('DB_USER') ?: ($configArquivo['user'] ?? 'root'),
            'password' => getenv('DB_PASS') ?: ($configArquivo['password'] ?? ''),
        ];
    }
}
