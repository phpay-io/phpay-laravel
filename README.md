# PHPay para Laravel

Bridge do [PHPay](https://github.com/phpay-io/phpay) para Laravel: o gateway vem
da configuração, e o webhook chega como evento.

> **Em construção.** A v0.1 ainda não foi publicada no Packagist.

## Por que um pacote separado

O `composer.json` do PHPay não tem dependência de framework, e isso é
deliberado: um bridge dentro do core forçaria Laravel em todo mundo que instala
a biblioteca. Então ele mora aqui.

## O que ele faz

**O gateway vem da config**, em vez de estar escrito no código:

```php
use PHPay\Laravel\Facades\Phpay;

Phpay::charge()->setCharge($dados)->create();        // o gateway de `default`
Phpay::gateway('woovi')->charge()->create(10050);    // outro, nomeado
```

```php
// config/phpay.php
'default'  => env('PHPAY_GATEWAY', 'asaas'),
'gateways' => [
    'asaas' => ['token' => env('ASAAS_TOKEN'), 'sandbox' => env('ASAAS_SANDBOX', true)],
],
```

**O webhook chega como evento**, já verificado e já lido:

```php
class LiberarPedido
{
    public function handle(WebhookEventReceived $received): void
    {
        if ($received->event->type === EventType::PAID) {
            Pedido::where('cobranca_id', $received->event->resourceId)->update(['pago' => true]);
        }
    }
}
```

## Instalação

```bash
composer require phpay-io/phpay-laravel
php artisan vendor:publish --tag=phpay-config
```

## Requisitos

PHP `^8.1` e Laravel 10, 11 ou 12 — o mesmo piso de PHP do core.

## Licença

[Business Source License 1.1](./LICENSE.md), a mesma do PHPay.
