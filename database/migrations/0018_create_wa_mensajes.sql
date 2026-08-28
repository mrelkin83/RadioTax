-- Tabla del MOTOR. message_id_externo único = primera barrera de idempotencia:
-- un webhook reintentado por Evolution choca contra el índice y se descarta.
CREATE TABLE IF NOT EXISTS wa_mensajes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT UNSIGNED NOT NULL,
    message_id_externo VARCHAR(128) DEFAULT NULL,
    direccion ENUM('entrante','saliente') NOT NULL,
    tipo ENUM('texto','audio','imagen','documento','sistema') NOT NULL DEFAULT 'texto',
    contenido TEXT DEFAULT NULL,
    media_ruta VARCHAR(255) DEFAULT NULL,
    media_mime VARCHAR(80) DEFAULT NULL,
    transcripcion TEXT DEFAULT NULL,
    tokens_entrada INT NOT NULL DEFAULT 0,
    tokens_salida INT NOT NULL DEFAULT 0,
    proveedor VARCHAR(40) DEFAULT NULL,
    modelo VARCHAR(120) DEFAULT NULL,
    latencia_ms INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wa_msg_externo (message_id_externo),
    KEY idx_wa_msg_conv (conversacion_id, id),
    CONSTRAINT fk_wa_mensajes_conversacion FOREIGN KEY (conversacion_id) REFERENCES wa_conversaciones (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
