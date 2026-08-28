-- Tabla del MOTOR (elkinlinan/whatsapp-ai-engine), no de la plataforma: no
-- lleva prefijo tx_. Se define aquí porque el motor no trae sus propias
-- migraciones (cada proyecto consumidor escribe la suya — ver
-- docs/ARQUITECTURA_Y_MODELO_DE_DATOS.md). Adaptada de la versión mono-tenant
-- de Control_BarMax: se le agregó empresa_id porque TAXIS es "una base, una
-- columna" (como MayTech), no "una base por negocio".
CREATE TABLE IF NOT EXISTS wa_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    canal_tipo VARCHAR(20) NOT NULL DEFAULT 'evolution',
    evolution_url VARCHAR(255) DEFAULT NULL,
    evolution_instancia VARCHAR(100) DEFAULT NULL,
    evolution_apikey TEXT DEFAULT NULL COMMENT 'Cifrada',
    webhook_token_hash CHAR(64) DEFAULT NULL COMMENT 'SHA-256 del token de la URL del webhook',
    numero_whatsapp VARCHAR(25) DEFAULT NULL,
    estado_conexion ENUM('desconectado','qr','conectado','error') NOT NULL DEFAULT 'desconectado',
    ultima_conexion DATETIME DEFAULT NULL,
    llm_proveedor VARCHAR(40) DEFAULT NULL,
    llm_modelo VARCHAR(120) DEFAULT NULL,
    llm_api_key TEXT DEFAULT NULL COMMENT 'Cifrada',
    llm_fallback_proveedor VARCHAR(40) DEFAULT NULL,
    llm_fallback_modelo VARCHAR(120) DEFAULT NULL,
    llm_fallback_api_key TEXT DEFAULT NULL COMMENT 'Cifrada',
    llm_max_tokens INT NOT NULL DEFAULT 2048,
    llm_temperatura DECIMAL(3,2) DEFAULT NULL,
    stt_proveedor VARCHAR(40) DEFAULT NULL,
    stt_api_key TEXT DEFAULT NULL COMMENT 'Cifrada',
    stt_modelo VARCHAR(120) DEFAULT NULL,
    tts_proveedor VARCHAR(40) DEFAULT NULL,
    tts_api_key TEXT DEFAULT NULL COMMENT 'Cifrada',
    tts_voice_id VARCHAR(120) DEFAULT NULL,
    tts_modelo VARCHAR(120) DEFAULT NULL,
    tts_modo ENUM('nunca','siempre','espejo','texto_y_audio') NOT NULL DEFAULT 'espejo',
    vision_proveedor VARCHAR(40) DEFAULT NULL,
    vision_api_key TEXT DEFAULT NULL COMMENT 'Cifrada',
    vision_modelo VARCHAR(120) DEFAULT NULL,
    horario_atencion TEXT DEFAULT NULL COMMENT 'JSON por dia; el motor lo consulta, no lo razona',
    handoff_numero VARCHAR(25) DEFAULT NULL,
    retencion_media_dias INT NOT NULL DEFAULT 7,
    limite_mensajes INT NOT NULL DEFAULT 15,
    limite_ventana_minutos INT NOT NULL DEFAULT 5,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wa_config_empresa (empresa_id),
    UNIQUE KEY uq_wa_config_token (webhook_token_hash),
    CONSTRAINT fk_wa_config_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
