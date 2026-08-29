-- Configuración de dónde opera la empresa (§ geolocalización): país y
-- departamento/estado, para completar lo que ya daba `ciudad`. Por ahora
-- el panel solo ofrece Colombia y Venezuela como países — el campo queda
-- como texto libre en la base para no atarse a un catálogo cerrado si el
-- negocio crece a otro país más adelante.
ALTER TABLE tx_empresas
    ADD COLUMN pais VARCHAR(60) NULL AFTER nombre,
    ADD COLUMN departamento VARCHAR(80) NULL AFTER pais;
