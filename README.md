# PHPay para Laravel

Bridge do [PHPay](https://github.com/phpay-io/phpay) para Laravel: o gateway vem
da configuração, e o webhook chega como evento.

> **Em construção.** A v0.1 ainda não foi publicada no Packagist, e o pacote
> aponta para o `dev-develop` do core enquanto a v2.5 não é cortada.

## Por que um pacote separado

O `composer.json` do PHPay não tem dependência de framework, e isso é
deliberado: um bridge dentro do core forçaria Laravel em todo mundo que instala
a biblioteca. Então ele mora aqui.

## Instalação

```bash
composer require phpay-io/phpay-laravel
php artisan vendor:publish --tag=phpay-config
```

Requisitos: PHP `^8.1` e Laravel 10, 11 ou 12 — o mesmo piso de PHP do core.

## O gateway vem da config

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

Quem usa um gateway só pode injetar a classe do core direto, sem ver o manager:

```php
public function __construct(private PHPay $phpay) {}
```

As chaves de cada gateway são as que ele exige, e **elas não coincidem**: seis
pedem uma credencial, três pedem duas, e Mercado Pago, Pagar.me e AbacatePay
**não aceitam `sandbox`** — o ambiente deles vem do prefixo da credencial.
Mandar a chave ali levanta exceção em vez de ser ignorada. O config publicado
traz os nove comentados.

Para ver o que está configurado e o que cada um faz:

```bash
php artisan phpay:gateways
```

## O webhook chega como evento

Com `webhooks.route` preenchida, o pacote registra uma rota que verifica a
assinatura, lê a notificação e despacha um `WebhookEventReceived` **por evento
lido**:

```php
use PHPay\Laravel\Events\WebhookEventReceived;
use PHPay\Webhooks\EventType;

class LiberarPedido
{
    public function handle(WebhookEventReceived $received): void
    {
        match ($received->event->type) {
            EventType::PAID     => $this->liberar($received->event->resourceId),
            EventType::REFUNDED => $this->estornar($received->event->resourceId),
            default             => null,
        };
    }
}
```

**Por evento lido, não por requisição.** Uma notificação pode trazer vários: a
API Pix da Efí manda um array de pagamentos recebidos, e o PagBank manda o
pedido com todas as cobranças.

O `Event` é `readonly` e serializa, então listener em fila funciona.

### Quando a notificação não diz o estado

Quatro gateways notificam que algo mudou e nada mais. O
`requiresLookup()` diz quando é preciso consultar de volta:

```php
if ($received->event->requiresLookup()) {
    $cobranca = Phpay::gateway($received->gateway)->charge()->find($received->event->resourceId);
}
```

### O gateway que não assina é recusado, não aceito em silêncio

Quatro dos nove não assinam a notificação. Registrar a rota e aceitar seria o
caminho para confirmação de pagamento forjada por quem descobrir a URL, então a
rota responde `501` — a menos que a config declare que você confere de outra
forma:

```php
'cielo' => ['unverified' => true],
```

| | assina | é lido |
| --- | :---: | :---: |
| Asaas, Woovi, Mercado Pago, PagBank | ✅ | ✅ |
| Efí, Pagar.me, Cielo | — | ✅ |
| AbacatePay | ✅ | — |
| Rede | — | — |

Deixe `webhooks.route` como `null` para cuidar disso no seu próprio controller,
o que é a escolha certa quando a notificação tem que fazer mais que despachar
eventos.

## Uma pegadinha de nome

A facade se chama **`Phpay`**, não `PHPay`. Nomes de classe em PHP são
insensíveis a caixa, então `PHPay\Laravel\Facades\PHPay` e o `PHPay\PHPay`
do core seriam **o mesmo nome**.

A consequência prática: num arquivo que importa a facade, você não pode
escrever um nome totalmente qualificado começando em `PHPay\` — ele resolve
sob o alias. Importe a classe:

```php
use PHPay\Asaas\Resources\Charge\Charge as AsaasCharge;   // ✅
// $x instanceof PHPay\Asaas\Resources\Charge\Charge      // ❌ resolve errado
```

## Desenvolvimento

```bash
composer install
composer test        # pint + pest + phpstan
```

A suíte usa [Testbench](https://github.com/orchestral/testbench) e **não acessa
a rede**: os gateways não fazem requisição ao serem construídos.

## Licença

[Business Source License 1.1](./LICENSE.md), a mesma do PHPay.
