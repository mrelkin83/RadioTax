-- `rol` y `personalidad` eran VARCHAR(200) mientras que sus campos hermanos
-- de texto libre (objetivo, instrucciones, saludo_inicial...) ya eran TEXT.
-- Un admin escribiendo una descripción de rol/personalidad un poco más larga
-- rompía el guardado con un 500 (SQLSTATE 22001: string data, right
-- truncated) en vez de un error claro. Se igualan al resto: sin límite
-- artificial.
ALTER TABLE wa_agentes
    MODIFY COLUMN rol TEXT DEFAULT NULL,
    MODIFY COLUMN personalidad TEXT DEFAULT NULL;
