CREATE TABLE IF NOT EXISTS tx_carrera_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    carrera_id INT UNSIGNED NOT NULL,
    evento VARCHAR(80) NOT NULL,
    actor_tipo ENUM('IA','SISTEMA','RADIOOPERADOR','ADMIN','CONDUCTOR') NOT NULL,
    actor_id INT UNSIGNED NULL,
    detalle JSON NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_eventos_carrera FOREIGN KEY (carrera_id) REFERENCES tx_carreras (id),
    INDEX idx_tx_eventos_carrera (carrera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
