-- Tabla del MOTOR. Con empresa_id (TAXIS es multi-empresa por columna).
-- Las REGLAS CRÍTICAS no viven aquí (viven en PromptComposer, dentro del
-- motor): esto es solo lo que cada empresa puede personalizar.
CREATE TABLE IF NOT EXISTS wa_agentes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    linea_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = atiende todas las líneas de la empresa',
    nombre VARCHAR(60) NOT NULL DEFAULT 'Asistente',
    rol VARCHAR(200) DEFAULT NULL,
    objetivo TEXT DEFAULT NULL,
    personalidad VARCHAR(200) DEFAULT NULL,
    genero VARCHAR(10) NOT NULL DEFAULT 'femenino',
    idioma VARCHAR(10) NOT NULL DEFAULT 'es',
    instrucciones TEXT DEFAULT NULL COMMENT 'Lo que escribe la empresa',
    herramientas TEXT DEFAULT NULL COMMENT 'JSON: lista blanca de tools habilitadas',
    saludo_inicial TEXT DEFAULT NULL,
    mensaje_fuera_horario TEXT DEFAULT NULL,
    mensaje_error TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wa_agentes_empresa FOREIGN KEY (empresa_id) REFERENCES tx_empresas (id),
    CONSTRAINT fk_wa_agentes_linea FOREIGN KEY (linea_id) REFERENCES tx_lineas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
