CREATE TABLE IF NOT EXISTS tx_clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    nombre VARCHAR(120) NULL,
    notas TEXT NULL,
    creado_por ENUM('IA','OPERADOR') NOT NULL DEFAULT 'IA',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tx_clientes_empresa_whatsapp (empresa_id, whatsapp),
    CONSTRAINT fk_tx_clientes_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
