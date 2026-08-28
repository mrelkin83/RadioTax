CREATE TABLE IF NOT EXISTS tx_lineas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    instancia_evolution VARCHAR(120) NOT NULL,
    token_webhook VARCHAR(191) NOT NULL,
    agentes_max INT UNSIGNED NOT NULL DEFAULT 1,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tx_lineas_token (token_webhook),
    CONSTRAINT fk_tx_lineas_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
