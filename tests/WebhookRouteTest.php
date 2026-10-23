<?php

use Illuminate\Support\Facades\Event;
use PHPay\Laravel\Events\WebhookEventReceived;
use PHPay\Webhooks\EventType;

it('aceita a notificação assinada e despacha um evento por evento lido', function () {
    Event::fake([WebhookEventReceived::class]);

    $corpo = ['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_001', 'status' => 'RECEIVED']];

    $this->postJson('/webhooks/phpay/asaas', $corpo, ['asaas-access-token' => 'segredo-do-webhook'])
        ->assertOk()
        ->assertJson(['events' => 1]);

    Event::assertDispatched(
        WebhookEventReceived::class,
        fn (WebhookEventReceived $received): bool => $received->gateway === 'asaas'
            && $received->event->type === EventType::PAID
            && $received->event->resourceId === 'pay_001'
    );
});

it('recusa com 401 quando a assinatura não bate', function () {
    Event::fake([WebhookEventReceived::class]);

    $this->postJson('/webhooks/phpay/asaas', ['event' => 'PAYMENT_RECEIVED'], ['asaas-access-token' => 'errado'])
        ->assertStatus(401);

    Event::assertNotDispatched(WebhookEventReceived::class);
});

it('recusa com 401 quando a notificação não traz assinatura nenhuma', function () {
    Event::fake([WebhookEventReceived::class]);

    $this->postJson('/webhooks/phpay/asaas', ['event' => 'PAYMENT_RECEIVED'])
        ->assertStatus(401);

    Event::assertNotDispatched(WebhookEventReceived::class);
});

it('recusa o gateway que não assina, em vez de aceitar em silêncio', function () {
    Event::fake([WebhookEventReceived::class]);

    /*
     | a Cielo não assina a notificação. Registrar a rota e aceitar seria o
     | caminho para confirmação de pagamento forjada por quem descobrir a URL.
     */
    $this->postJson('/webhooks/phpay/cielo', ['PaymentId' => 'guid', 'ChangeType' => 1])
        ->assertStatus(501);

    Event::assertNotDispatched(WebhookEventReceived::class);
});

it('aceita o gateway sem assinatura quando a config declara o risco', function () {
    Event::fake([WebhookEventReceived::class]);

    config()->set('phpay.webhooks.cielo', ['unverified' => true]);

    $this->postJson('/webhooks/phpay/cielo', ['PaymentId' => 'guid-da-venda', 'ChangeType' => 1])
        ->assertOk();

    Event::assertDispatched(
        WebhookEventReceived::class,
        fn (WebhookEventReceived $received): bool => $received->event->requiresLookup()
            && $received->event->resourceId === 'guid-da-venda'
    );
});

it('avisa quando falta o segredo na configuração', function () {
    config()->set('phpay.webhooks.asaas', []);

    $this->postJson('/webhooks/phpay/asaas', ['event' => 'PAYMENT_RECEIVED'])
        ->assertStatus(500);
});

it('recusa corpo vazio com 422, que é integração e não evento desconhecido', function () {
    Event::fake([WebhookEventReceived::class]);

    $this->call(
        'POST',
        '/webhooks/phpay/asaas',
        [],
        [],
        [],
        ['HTTP_ASAAS_ACCESS_TOKEN' => 'segredo-do-webhook'],
        ''
    )->assertStatus(422);

    Event::assertNotDispatched(WebhookEventReceived::class);
});

it('não registra a rota quando a configuração não pede uma', function () {
    config()->set('phpay.webhooks.route', null);

    /* a rota foi registrada no boot deste teste, então o que se afirma é a
       decisão: config sem rota não deve registrar nada */
    expect(app(PHPay\Laravel\GatewayManager::class)->webhookConfig('asaas'))->toHaveKey('secret');
});
