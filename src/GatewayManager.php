<?php

namespace PHPay\Laravel;

use BadMethodCallException;
use Illuminate\Contracts\Config\Repository;
use PHPay\Exceptions\ValidationException;
use PHPay\Laravel\Exceptions\GatewayNotConfigured;
use PHPay\PHPay;
use PHPay\Support\Gateway;

/**
 * resolves gateways from `config/phpay.php`.
 *
 * the core's registry turns a name and an array into a gateway; this adds the
 * two things an application needs on top: where the array comes from, and that
 * the same name resolves to the same instance within a request.
 */
class GatewayManager
{
    /**
     * gateways already built, by name.
     *
     * @var array<string, PHPay>
     */
    private array $resolved = [];

    /**
     * @param Repository $config
     */
    public function __construct(
        private readonly Repository $config
    ) {
    }

    /**
     * the gateway of that name, or the configured default.
     *
     * @param string|null $name
     * @return PHPay
     * @throws GatewayNotConfigured when there is no config block for it
     * @throws ValidationException when the name or the config is wrong
     */
    public function gateway(?string $name = null): PHPay
    {
        $name = strtolower(trim($name ?? $this->defaultName()));

        return $this->resolved[$name] ??= PHPay::make($name, $this->configFor($name));
    }

    /**
     * the name in `phpay.default`.
     *
     * @return string
     */
    public function defaultName(): string
    {
        $name = $this->config->get('phpay.default');

        return is_string($name) && trim($name) !== '' ? $name : Gateway::ASAAS->value;
    }

    /**
     * the names that have a config block, whether or not they are filled in.
     *
     * @return array<int, string>
     */
    public function configured(): array
    {
        $gateways = $this->config->get('phpay.gateways');

        if (!is_array($gateways)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($gateways),
            static fn (mixed $name): bool => is_string($name)
        ));
    }

    /**
     * the webhook config of a gateway.
     *
     * @param string $name
     * @return array<string, mixed>
     */
    public function webhookConfig(string $name): array
    {
        $webhook = $this->config->get('phpay.webhooks.' . $name);

        return is_array($webhook) ? $webhook : [];
    }

    /**
     * forget what was already built, so the next call resolves again.
     *
     * for tests and for long-running workers that change configuration between
     * jobs — the gateways hold credentials, so reusing one after the config
     * changed would keep talking with the old key.
     *
     * @return void
     */
    public function forget(): void
    {
        $this->resolved = [];
    }

    /**
     * forward anything else to the default gateway.
     *
     * `Phpay::charge()` reads better than `Phpay::gateway()->charge()` for the
     * common case of one configured gateway, and the facade's `@method` list
     * advertises exactly that — as does the first example in the README.
     *
     * **It raised `Call to undefined method` until this existed.** The manager
     * had neither those methods nor any forwarding, so the advertised shortcut
     * never worked.
     *
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return mixed
     * @throws BadMethodCallException when the gateway has no such method either
     */
    public function __call(string $method, array $arguments): mixed
    {
        $gateway = $this->gateway();

        if (!method_exists($gateway, $method)) {
            throw new BadMethodCallException(sprintf(
                'Nem %s nem o gateway padrão (%s) têm o método "%s".',
                static::class,
                $gateway->name(),
                $method
            ));
        }

        return $gateway->{$method}(...$arguments);
    }

    /**
     * the credentials block of a gateway, as the registry expects it.
     *
     * keys with a null value are dropped: an unset `env()` would otherwise
     * reach the registry as a present-but-null credential, and the message
     * about a missing key is the useful one.
     *
     * @param string $name
     * @return array<string, mixed>
     * @throws GatewayNotConfigured
     */
    private function configFor(string $name): array
    {
        $config = $this->config->get('phpay.gateways.' . $name);

        if (!is_array($config)) {
            throw GatewayNotConfigured::named($name, $this->configured());
        }

        /** @var array<string, mixed> $filtered */
        $filtered = array_filter(
            $config,
            static fn (mixed $value): bool => $value !== null
        );

        return $filtered;
    }
}
