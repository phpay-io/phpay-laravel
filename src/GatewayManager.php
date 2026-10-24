<?php

namespace PHPay\Laravel;

use Closure;
use GuzzleHttp\Handler\MockHandler;
use Illuminate\Contracts\Config\Repository;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\ClientFactory;
use PHPay\Laravel\Exceptions\GatewayNotConfigured;
use PHPay\PHPay;
use PHPay\Support\Gateway;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Throwable;

/**
 * resolves gateways from `config/phpay.php`.
 *
 * the core's registry turns a name and an array into a gateway; this adds the
 * three things an application needs on top: where the array comes from, that
 * the same name resolves to the same instance within a request, and the fake
 * mode plus its assertions.
 *
 * the fake lives here rather than in a separate class for one reason: turning
 * it on has to drop the resolved-gateway cache, and that cache is this class's.
 * A fake that could not reach it would hand back gateways born before the fake,
 * still pointed at the network.
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
     * answer from a queue instead of from the network.
     *
     * thin over the core's `ClientFactory::fake()`, which fakes **every**
     * client the library builds — including the second ones Efí, Cielo and
     * Rede keep for another host. What this adds is dropping the resolved
     * cache: a gateway built before the fake carries a real client, and
     * without this the first `Phpay::gateway()` of the test would go out.
     *
     * ```php
     * Phpay::fake([new Response(200, [], json_encode(['id' => 'pay_001']))]);
     *
     * $pedido->pagar();
     *
     * Phpay::assertSent(fn (RequestInterface $r) => $r->getMethod() === 'POST');
     * ```
     *
     * @param array<int, ResponseInterface|Throwable>|MockHandler $responses
     * @return MockHandler
     */
    public function fake(array|MockHandler $responses = []): MockHandler
    {
        $this->forget();

        return ClientFactory::fake($responses);
    }

    /**
     * back to the network, with the history cleared.
     *
     * @return void
     */
    public function stopFaking(): void
    {
        ClientFactory::stopFaking();

        $this->forget();
    }

    /**
     * whether the factory is answering from a queue.
     *
     * @return bool
     */
    public function faking(): bool
    {
        return ClientFactory::faking();
    }

    /**
     * the requests the application made while faking.
     *
     * @return array<int, array{request: RequestInterface, options: array<mixed>}>
     */
    public function recorded(): array
    {
        return ClientFactory::recorded();
    }

    /**
     * at least one request matched.
     *
     * @param Closure(RequestInterface, array<mixed>): bool $callback
     * @param int|null $times exactly this many, when given
     * @return void
     */
    public function assertSent(Closure $callback, ?int $times = null): void
    {
        $matched = array_values(array_filter(
            $this->recorded(),
            static fn (array $sent): bool => $callback($sent['request'], $sent['options'])
        ));

        if ($times !== null) {
            Assert::assertCount($times, $matched, sprintf(
                'Esperava %d requisição(ões) casando com o filtro, e %d casaram.%s',
                $times,
                count($matched),
                $this->summary()
            ));

            return;
        }

        Assert::assertNotEmpty($matched, 'Nenhuma requisição casou com o filtro.' . $this->summary());
    }

    /**
     * nothing went out.
     *
     * @return void
     */
    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->recorded(), 'Esperava nenhuma requisição.' . $this->summary());
    }

    /**
     * exactly this many requests went out.
     *
     * @param int $count
     * @return void
     */
    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->recorded(), sprintf(
            'Esperava %d requisição(ões).%s',
            $count,
            $this->summary()
        ));
    }

    /**
     * what did go out, for the failure message.
     *
     * a fake is only pleasant when the failure says what happened instead of
     * just that it did not match.
     *
     * @return string
     */
    private function summary(): string
    {
        $sent = $this->recorded();

        if ($sent === []) {
            return ' Nada foi enviado.';
        }

        $lines = array_map(
            static fn (array $item): string => sprintf(
                '  %s %s',
                $item['request']->getMethod(),
                (string) $item['request']->getUri()
            ),
            $sent
        );

        return sprintf(" Foram enviadas %d:\n%s", count($sent), implode("\n", $lines));
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
