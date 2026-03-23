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
        ];

        foreach ($queries as $query) {
            $conn->exec($query);
        }

        if (!$this->colunaExiste($conn, 'clientes', 'endereco')) {
            $conn->exec('ALTER TABLE clientes ADD COLUMN endereco VARCHAR(220) NOT NULL DEFAULT "" AFTER email');
        }

    }

    private function colunaExiste(PDO $conn, string $tabela, string $coluna): bool
    {
        $stmt = $conn->prepare(sprintf('SHOW COLUMNS FROM `%s` LIKE :coluna', str_replace('`', '', $tabela)));
        $stmt->bindValue(':coluna', $coluna);
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
