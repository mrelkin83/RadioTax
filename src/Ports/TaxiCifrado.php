<?php

declare(strict_types=1);

namespace TaxiApp\Ports;

use RuntimeException;

/**
 * Pendiente: conformar al SecretPort real del motor. Hoy cifra/descifra
 * con libsodium usando APP_SECRET_KEY (env) para que los puertos que lo
 * necesiten (ej. tokens de línea) no guarden nada en claro.
 */
final class TaxiCifrado
{
    private readonly string $llave;

    public function __construct(?string $llave = null)
    {
        $llave ??= getenv('APP_SECRET_KEY') ?: '';
        if ($llave === '') {
            throw new RuntimeException('APP_SECRET_KEY no configurada.');
        }

        $this->llave = hash('sha256', $llave, true);
    }

    public function cifrar(string $texto): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cifrado = sodium_crypto_secretbox($texto, $nonce, $this->llave);

        return base64_encode($nonce . $cifrado);
    }

    public function descifrar(string $texto): string
    {
        $datos = base64_decode($texto, true);
        if ($datos === false || strlen($datos) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Texto cifrado inválido.');
        }

        $nonce = substr($datos, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cifrado = substr($datos, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $texto = sodium_crypto_secretbox_open($cifrado, $nonce, $this->llave);

        if ($texto === false) {
            throw new RuntimeException('No se pudo descifrar el texto.');
        }

        return $texto;
    }
}
