CREATE TABLE IF NOT EXISTS tx_config_despacho (
    empresa_id INT UNSIGNED NOT NULL PRIMARY KEY,
    modo ENUM('AUTOMATICO','HIBRIDO','MANUAL') NOT NULL DEFAULT 'HIBRIDO',
    criterios_ranking JSON NULL,
    timeouts JSON NULL,
    reglas_particulares JSON NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_config_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
