CREATE TABLE IF NOT EXISTS tx_ubicaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT UNSIGNED NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(10,7) NOT NULL,
    precision_metros DECIMAL(6,2) NULL,
    reportado_en DATETIME NOT NULL,
    CONSTRAINT fk_tx_ubicaciones_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES tx_vehiculos (id),
    INDEX idx_tx_ubicaciones_vehiculo_fecha (vehiculo_id, reportado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
