-- SUPERADMIN: el dueño de la plataforma, no de una empresa. Da de alta
-- empresas nuevas (marca blanca real, §7 del system prompt maestro:
-- "Radio Tax es el primer tenant, no el único"). empresa_id pasa a ser
-- opcional porque un SUPERADMIN no pertenece a ninguna empresa.
-- ALTER...MODIFY es idempotente en MySQL: re-aplicar la misma definición
-- no falla si esta migración ya corrió.
ALTER TABLE tx_usuarios
    MODIFY COLUMN empresa_id INT UNSIGNED NULL,
    MODIFY COLUMN rol ENUM('RADIOOPERADOR','ADMIN','SUPERADMIN') NOT NULL DEFAULT 'RADIOOPERADOR';
