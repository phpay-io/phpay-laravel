<?php

namespace PHPay\Laravel\Exceptions;

use InvalidArgumentException;

/**
 * the application asked for a gateway that has no block in `config/phpay.php`.
 *
 * separate from the core's `ValidationException`, which is about a payload the
 * gateway refused. This one is about the application's own configuration, and
 * the fix is in a file the developer owns.
 */
final class GatewayNotConfigured extends InvalidArgumentException
{
    /**
     * the config carries half of a certificate pair.
     *
     * also the application's own configuration: Inter issues the certificate
     * and the private key as two files, and filling one env var without the
     * other is a mistake worth naming. Dropping it in silence would surface
     * later as "this gateway needs a certificate", which points at the wrong
     * place entirely.
     *
     * @param string $name
     * @return self
     */
    public static function halfCertificate(string $name): self
    {
        return new self(sprintf(
            'O gateway "%s" tem a chave privada configurada, mas não o certificado. '
            . 'Quem entrega os dois arquivos precisa dos dois: preencha "certificate" também.',
            $name
        ));
    }

    /**
     * @param string $name
     * @param array<int, string> $configured
     * @return self
     */
    public static function named(string $name, array $configured): self
    {
        return new self(sprintf(
            'O gateway "%s" não tem configuração em config/phpay.php. Configurados: %s.',
            $name,
            $configured === [] ? 'nenhum' : implode(', ', $configured)
        ));
    }
}
