-- Tabla del MOTOR. empresa_id la usa Scope::y() para que un mismo número de
-- WhatsApp escribiéndole a dos empresas distintas no comparta conversación.
CREATE TABLE IF NOT EXISTS wa_conversaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    linea_id INT UNSIGNED DEFAULT NULL,
    telefono VARCHAR(25) NOT NULL,
    cliente_id INT UNSIGNED DEFAULT NULL COMMENT 'tx_clientes.id, lo resuelve TaxiAdapter',
    nombre_contacto VARCHAR(100) DEFAULT NULL,
    estado ENUM('IA_ACTIVA','IA_PAUSADA','HUMANO_ATENDIENDO','CERRADA') NOT NULL DEFAULT 'IA_ACTIVA',
    agente_id INT UNSIGNED DEFAULT NULL,
    atendida_por INT UNSIGNED DEFAULT NULL COMMENT 'tx_usuarios.id que tomó la conversación',
    contexto TEXT DEFAULT NULL COMMENT 'JSON: datos a medio confirmar',
    ultimo_mensaje_at DATETIME DEFAULT NULL,
    limite_avisado_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wa_conv_telefono (telefono),
    KEY idx_wa_conv_estado (estado, ultimo_mensaje_at),
    KEY idx_wa_conv_empresa (empresa_id),
    CONSTRAINT fk_wa_conv_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id),
    CONSTRAINT fk_wa_conv_linea FOREIGN KEY (linea_id) REFERENCES tx_lineas (id),
    CONSTRAINT fk_wa_conv_cliente FOREIGN KEY (cliente_id) REFERENCES tx_clientes (id),
    CONSTRAINT fk_wa_conv_agente FOREIGN KEY (agente_id) REFERENCES wa_agentes (id),
    CONSTRAINT fk_wa_conv_atendida_por FOREIGN KEY (atendida_por) REFERENCES tx_usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
