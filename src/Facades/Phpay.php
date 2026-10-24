<?php

namespace PHPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PHPay\Laravel\GatewayManager;
use PHPay\PHPay as CorePHPay;

/**
 * the manager, as a facade.
 *
 * spelled `Phpay` and not `PHPay` on purpose: the core's entry point is
 * `PHPay\PHPay`, and class names are case-insensitive in PHP — the two would
 * be the same name. That is not a style preference: this very file cannot
 * import the core's class without aliasing it, which is why it appears below
 * as `CorePHPay`.
 *
 * @method static CorePHPay gateway(?string $name = null)
 * @method static string defaultName()
 * @method static array<int, string> configured()
 * @method static array<string, mixed> webhookConfig(string $name)
 * @method static void forget()
 * @method static \GuzzleHttp\Handler\MockHandler fake(array<int, \Psr\Http\Message\ResponseInterface|\Throwable>|\GuzzleHttp\Handler\MockHandler $responses = [])
 * @method static void stopFaking()
 * @method static bool faking()
 * @method static array<int, array{request: \Psr\Http\Message\RequestInterface, options: array<mixed>}> recorded()
 * @method static void assertSent(\Closure $callback, ?int $times = null)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static object charge()
 * @method static object customer(mixed $customer = [])
 * @method static object subscription()
 * @method static object pix()
 * @method static object webhook(array<mixed> $webhook = [])
 * @method static object webhookSignature()
 * @method static array<mixed> refund(string $id, mixed $amount = null)
 * @method static array<int, \PHPay\Webhooks\Event> webhookEvents(string|array<mixed> $payload)
 * @method static string name()
 * @method static bool supports(\PHPay\Contracts\Capability $capability)
 * @method static array<int, \PHPay\Contracts\Capability> capabilities()
 *
 * @see GatewayManager
 */
class Phpay extends Facade
{
    /**
     * the container binding behind the facade.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return GatewayManager::class;
    }
}
