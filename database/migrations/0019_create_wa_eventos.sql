-- Tabla del MOTOR. Auditoría del motor (mensajes, llamadas al LLM, tools,
-- handoff, errores) — distinta de tx_carrera_eventos, que es la trazabilidad
-- de negocio de la plataforma. payload pasa por el sanitizador de secretos
-- del motor: ni una API key toca esta tabla.
CREATE TABLE IF NOT EXISTS wa_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    conversacion_id INT UNSIGNED DEFAULT NULL,
    tipo ENUM('mensaje','llm','tool','handoff','error','webhook','config') NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    payload LONGTEXT DEFAULT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wa_ev_conv (conversacion_id, id),
    KEY idx_wa_ev_tipo (tipo, created_at),
    KEY idx_wa_ev_empresa (empresa_id),
    CONSTRAINT fk_wa_eventos_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
