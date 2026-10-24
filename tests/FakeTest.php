<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPay\Laravel\Facades\Phpay;
use Psr\Http\Message\RequestInterface;

/* estado estático no core: nenhum teste deixa o modo falso ligado */
afterEach(function () {
    Phpay::stopFaking();
});

/**
 * @param array<mixed> $body
 * @return Response
 */
function resposta(array $body = [], int $status = 200): Response
{
    return new Response($status, ['content-type' => 'application/json'], (string) json_encode($body));
}

it('responde da fila em vez da rede', function () {
    Phpay::fake([resposta(['id' => 'pay_001', 'status' => 'PENDING'])]);

    $cobranca = Phpay::charge()
        ->setCharge(['customer' => 'cus_1', 'billingType' => 'PIX', 'value' => 100, 'dueDate' => '2026-12-01'])
        ->create();

    expect($cobranca['id'])->toBe('pay_001')
        ->and(Phpay::faking())->toBeTrue();
});

it('descarta o gateway que já estava resolvido', function () {
    /*
     * é o que o fake do bridge adiciona sobre o do core: o manager guarda a
     * instância por nome, e um gateway nascido antes do fake carrega client
     * real. Sem descartar, esta primeira chamada iria para a rede.
     */
    $antes = Phpay::gateway();

    Phpay::fake([resposta(['id' => 'pay_001'])]);

    expect(Phpay::gateway())->not->toBe($antes)
        ->and(Phpay::charge()->find('pay_001')['id'])->toBe('pay_001');
});

it('afirma o que foi enviado', function () {
    Phpay::fake([resposta(['id' => 'pay_001'])]);

    Phpay::charge()
        ->setCharge(['customer' => 'cus_1', 'billingType' => 'PIX', 'value' => 100, 'dueDate' => '2026-12-01'])
        ->create();

    Phpay::assertSentCount(1);
    Phpay::assertSent(fn (RequestInterface $request): bool => $request->getMethod() === 'POST');
    Phpay::assertSent(
        fn (RequestInterface $request): bool => str_ends_with((string) $request->getUri(), '/payments'),
        times: 1
    );
});

it('afirma que nada saiu', function () {
    Phpay::fake();

    Phpay::assertNothingSent();
});

it('a mensagem de falha diz o que foi enviado', function () {
    Phpay::fake([resposta(['id' => 'pay_001'])]);

    Phpay::charge()->find('pay_001');

    /* um fake só é agradável quando a falha conta o que aconteceu */
    expect(fn () => Phpay::assertNothingSent())
        ->toThrow(Exception::class, 'Foram enviadas 1');

    expect(fn () => Phpay::assertSent(fn (RequestInterface $r): bool => $r->getMethod() === 'DELETE'))
        ->toThrow(Exception::class, 'Nenhuma requisição casou');
});

it('falseia os dois hosts de um gateway que tem dois clients', function () {
    /* a Rede pede token num host e gasta noutro, antes de cada chamada */
    config()->set('phpay.gateways.rede', ['affiliation' => '123', 'secret' => 'segredo', 'sandbox' => true]);

    Phpay::fake([
        resposta(['access_token' => 'tok', 'token_type' => 'Bearer', 'expires_in' => 3600]),
        resposta(['returnCode' => '00', 'tid' => 'tid_1']),
    ]);

    $transacao = Phpay::gateway('rede')->charge()->find('tid_1');

    expect($transacao['returnCode'])->toBe('00');

    Phpay::assertSentCount(2);
    Phpay::assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), 'oauth'), times: 1);
});

it('aceita um MockHandler montado pelo teste', function () {
    $handler = new MockHandler([resposta(['id' => 'pay_001'])]);

    expect(Phpay::fake($handler))->toBe($handler);

    Phpay::charge()->find('pay_001');

    Phpay::assertSentCount(1);
});

it('para de falsear e limpa o histórico', function () {
    Phpay::fake([resposta(['id' => 'pay_001'])]);
    Phpay::charge()->find('pay_001');

    expect(Phpay::recorded())->toHaveCount(1);

    Phpay::stopFaking();

    expect(Phpay::faking())->toBeFalse()
        ->and(Phpay::recorded())->toBe([]);
});

it('o webhook do app pode ser testado sem rede', function () {
    /*
     * o caso que motivou tudo: testar o próprio endpoint de webhook, com a
     * assinatura verificada e o evento despachado, sem falar com o gateway.
     */
    Phpay::fake();

    $this->postJson(
        '/webhooks/phpay/asaas',
        ['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_001', 'status' => 'RECEIVED']],
        ['asaas-access-token' => 'segredo-do-webhook']
    )->assertOk();

    /* verificar e ler webhook não faz HTTP: é assinatura e parsing */
    Phpay::assertNothingSent();
});
