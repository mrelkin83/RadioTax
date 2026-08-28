CREATE TABLE IF NOT EXISTS tx_vehiculo_conductor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT UNSIGNED NOT NULL,
    conductor_id INT UNSIGNED NOT NULL,
    fecha_desde DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hasta DATETIME NULL,
    CONSTRAINT fk_tx_vc_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES tx_vehiculos (id),
    CONSTRAINT fk_tx_vc_conductor FOREIGN KEY (conductor_id) REFERENCES tx_conductores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
