CREATE TABLE IF NOT EXISTS tx_asignaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    carrera_id INT UNSIGNED NOT NULL,
    vehiculo_id INT UNSIGNED NOT NULL,
    propuesto_por ENUM('IA','SISTEMA','RADIOOPERADOR') NOT NULL,
    decidido_por ENUM('IA','SISTEMA','RADIOOPERADOR') NOT NULL,
    resultado ENUM('ACEPTADA','RECHAZADA','SIN_RESPUESTA') NOT NULL DEFAULT 'SIN_RESPUESTA',
    medio ENUM('RADIO','WHATSAPP','APP','MANUAL') NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_asignaciones_carrera FOREIGN KEY (carrera_id) REFERENCES tx_carreras (id),
    CONSTRAINT fk_tx_asignaciones_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES tx_vehiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
