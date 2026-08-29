-- Liberación automática de vehículos: cuando se cumple el tiempo estimado
-- de un servicio (llegada del taxi al punto de recogida + recorrido hasta
-- el destino, con margen), el vehículo vuelve a DISPONIBLE sin que el
-- radiooperador tenga que marcarlo a mano. El operador sigue pudiendo
-- liberarlo manualmente en cualquier momento (selector de estado del
-- vehículo) — vehiculo_liberado_en/por registra cuál de los dos caminos
-- fue el que realmente lo liberó, para no volver a procesarlo dos veces.
ALTER TABLE tx_carreras
    ADD COLUMN estimado_liberacion_en DATETIME NULL AFTER asignada_en,
    ADD COLUMN vehiculo_liberado_en DATETIME NULL AFTER estimado_liberacion_en,
    ADD COLUMN vehiculo_liberado_por ENUM('AUTOMATICO', 'MANUAL') NULL AFTER vehiculo_liberado_en;

-- Parámetros de la estimación, configurables por empresa porque el tiempo
-- de llegada del taxi varía de una ciudad a otra (piloto: Arauca, 5 a 10
-- minutos). NULL = usar los valores por defecto del código.
ALTER TABLE tx_empresas
    ADD COLUMN tiempo_llegada_taxi_min SMALLINT UNSIGNED NULL AFTER departamento,
    ADD COLUMN velocidad_promedio_kmh SMALLINT UNSIGNED NULL AFTER tiempo_llegada_taxi_min;
