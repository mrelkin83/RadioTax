CREATE TABLE IF NOT EXISTS tx_conductores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    documento VARCHAR(30) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20) NULL,
    estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tx_conductores_empresa_doc (empresa_id, documento),
    CONSTRAINT fk_tx_conductores_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
