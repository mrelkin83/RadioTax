CREATE TABLE IF NOT EXISTS tx_turnos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conductor_id INT UNSIGNED NOT NULL,
    vehiculo_id INT UNSIGNED NOT NULL,
    inicio DATETIME NOT NULL,
    fin DATETIME NULL,
    abierto_por ENUM('OPERADOR','CONDUCTOR','SISTEMA') NOT NULL DEFAULT 'OPERADOR',
    CONSTRAINT fk_tx_turnos_conductor FOREIGN KEY (conductor_id) REFERENCES tx_conductores (id),
    CONSTRAINT fk_tx_turnos_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES tx_vehiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
