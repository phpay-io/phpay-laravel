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
