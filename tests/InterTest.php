<?php

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use PHPay\Contracts\Capability;
use PHPay\Exceptions\ValidationException;
use PHPay\Inter\InterGateway;
use PHPay\Laravel\Events\WebhookEventReceived;
use PHPay\Laravel\Exceptions\GatewayNotConfigured;
use PHPay\Laravel\Facades\Phpay;
use PHPay\Laravel\GatewayManager;
use PHPay\Support\{Gateway, Money};
use PHPay\Webhooks\EventType;

/**
 * um arquivo temporário com a extensão pedida.
 *
 * nada lê o conteúdo: quem lê é o cURL, na hora da requisição, e nenhum teste
 * sai para a rede.
 *
 * @param string $extension
 * @return string
 */
function interFile(string $extension): string
{
    $path = sys_get_temp_dir() . '/phpay-bridge-' . bin2hex(random_bytes(6)) . ".{$extension}";

    file_put_contents($path, 'conteúdo');

    register_shutdown_function(static fn () => @unlink($path));

    return $path;
}

/**
 * configura o Inter e devolve os caminhos do par.
 *
 * @param array<string, mixed> $extra
 * @return array{0: string, 1: string}
 */
function configureInter(array $extra = []): array
{
    $crt = interFile('crt');
    $key = interFile('key');

    config()->set('phpay.gateways.inter', [
        'clientId'     => 'fake-client-id',
        'clientSecret' => 'fake-client-secret',
        'sandbox'      => true,
        'certificate'  => $crt,
        'privateKey'   => $key,
    ] + $extra);

    return [$crt, $key];
}

/**
 * as opções do Guzzle que o client do gateway recebeu.
 *
 * @param object $gateway
 * @param string $option
 * @return mixed
 */
function interOption(object $gateway, string $option): mixed
{
    $resource = (new ReflectionProperty($gateway, 'certificate'))->getValue($gateway);

    return $resource?->guzzleOptions()[$option] ?? null;
}

it('resolve o inter pela configuração, com as três capacidades', function () {
    configureInter();

    $inter = app(GatewayManager::class)->gateway('inter');

    expect($inter->name())->toBe('Inter')
        ->and($inter->supports(Capability::CHARGES))->toBeTrue()
        ->and($inter->supports(Capability::WEBHOOKS))->toBeTrue()
        ->and($inter->supports(Capability::WEBHOOK_EVENTS))->toBeTrue()
        ->and($inter->supports(Capability::WEBHOOK_SIGNATURE))->toBeFalse()
        ->and($inter->supports(Capability::REFUNDS))->toBeFalse();
});

it('monta o par de certificado a partir das duas variáveis', function () {
    /*
     | o registry do core aceita `certificate` como caminho de arquivo único ou
     | como objeto — nunca como par. Quem junta as duas metades é o bridge.
     */
    [$crt, $key] = configureInter();

    $gateway = app(GatewayManager::class)->gateway('inter');
    $core    = (new ReflectionProperty($gateway, 'gateway'))->getValue($gateway);

    expect($core)->toBeInstanceOf(InterGateway::class)
        ->and(interOption($core, 'cert'))->toBe($crt)
        ->and(interOption($core, 'ssl_key'))->toBe($key);
});

it('num par a senha vai na chave, nunca no certificado', function () {
    /* o Guzzle transforma o segundo elemento de `cert` em senha de certificado,
       e num par o .crt é público: não tem o que decifrar */
    [$crt, $key] = configureInter(['passphrase' => 'segredo']);

    $core = (new ReflectionProperty(app(GatewayManager::class)->gateway('inter'), 'gateway'))
        ->getValue(app(GatewayManager::class)->gateway('inter'));

    expect(interOption($core, 'cert'))->toBe($crt)
        ->and(interOption($core, 'ssl_key'))->toBe([$key, 'segredo']);
});

it('aceita um .p12 sozinho, para quem já converteu', function () {
    $p12 = interFile('p12');

    config()->set('phpay.gateways.inter', [
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'certificate'  => $p12,
        'passphrase'   => 'segredo',
    ]);

    $core = (new ReflectionProperty(app(GatewayManager::class)->gateway('inter'), 'gateway'))
        ->getValue(app(GatewayManager::class)->gateway('inter'));

    expect(interOption($core, 'cert'))->toBe([$p12, 'segredo'])
        ->and(interOption($core, 'ssl_key'))->toBeNull();
});

it('não repassa privateKey nem passphrase ao core', function () {
    /*
     | o core recusa chave de configuração que não conhece, e é essa recusa que
     | pega typo. Se o bridge repassasse estas duas, o gateway não construiria.
     */
    configureInter(['passphrase' => 'segredo']);

    expect(fn () => app(GatewayManager::class)->gateway('inter'))->not->toThrow(ValidationException::class);

    /* FQN inline aqui resolveria sob o alias `Phpay` da facade, porque nome de
       classe em PHP é insensível a caixa: a classe tem de ser importada */
    $aceitas = Gateway::INTER->options();

    expect($aceitas)->not->toContain('privateKey')
        ->and($aceitas)->not->toContain('passphrase');
});

it('chave privada sem certificado é erro de configuração, com mensagem própria', function () {
    config()->set('phpay.gateways.inter', [
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'privateKey'   => interFile('key'),
    ]);

    expect(fn () => app(GatewayManager::class)->gateway('inter'))
        ->toThrow(GatewayNotConfigured::class, 'mas não o certificado');
});

it('certificado que não existe falha na resolução, não no boot do framework', function () {
    /* é o motivo de a montagem não viver no config/: lá quebraria todo artisan */
    config()->set('phpay.gateways.inter', [
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'certificate'  => '/nao/existe/certificado.crt',
        'privateKey'   => '/nao/existe/chave.key',
    ]);

    expect(fn () => app(GatewayManager::class)->gateway('inter'))
        ->toThrow(ValidationException::class, 'Certificado');
});

it('sem certificado o gateway constrói, e o erro vem quando o recurso monta o client', function () {
    config()->set('phpay.gateways.inter', ['clientId' => 'id', 'clientSecret' => 'secret']);

    $inter = app(GatewayManager::class)->gateway('inter');

    expect($inter->name())->toBe('Inter')
        ->and(fn () => $inter->charge())->toThrow(ValidationException::class, 'Certificate::pair');
});

it('credencial faltando é recusada com a lista de chaves aceitas', function () {
    config()->set('phpay.gateways.inter', ['clientId' => 'id']);

    expect(fn () => app(GatewayManager::class)->gateway('inter'))
        ->toThrow(ValidationException::class, 'clientSecret');
});

it('escopo e conta corrente chegam ao gateway', function () {
    configureInter(['scopes' => 'cob.read webhook.read', 'account' => '123456']);

    $core = (new ReflectionProperty(app(GatewayManager::class)->gateway('inter'), 'gateway'))
        ->getValue(app(GatewayManager::class)->gateway('inter'));

    expect($core->scopes())->toBe('cob.read webhook.read');

    /* sem escopo configurado, cai na união que as capacidades usam */
    configureInter();
    app(GatewayManager::class)->forget();

    $padrao = (new ReflectionProperty(app(GatewayManager::class)->gateway('inter'), 'gateway'))
        ->getValue(app(GatewayManager::class)->gateway('inter'));

    expect($padrao->scopes())->toBe(InterGateway::SCOPES);
});

it('cria uma cobrança pix pelo bridge, sem sair para a rede', function () {
    configureInter();

    Phpay::fake([
        new Response(200, [], (string) json_encode([
            'access_token' => 'tok',
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
        ])),
        new Response(200, [], (string) json_encode(['txid' => 'pedido-1', 'status' => 'ATIVA'])),
    ]);

    $cobranca = Phpay::gateway('inter')->charge()
        ->setKey('chave@phpay.io')
        ->setAmount(Money::reais('110,00'))
        ->create();

    expect($cobranca['txid'])->toBe('pedido-1');

    /* o token primeiro, a cobrança depois — e as duas no host de sandbox */
    Phpay::assertSentCount(2);
    Phpay::assertSent(fn ($request) => str_ends_with((string) $request->getUri(), 'oauth/v2/token'));
    Phpay::assertSent(fn ($request) => str_contains((string) $request->getUri(), 'uatinter.co')
        && str_ends_with((string) $request->getUri(), 'pix/v2/cob'));

    Phpay::stopFaking();
});

it('a rota de webhook recusa o inter com 501, porque ele não assina', function () {
    Event::fake([WebhookEventReceived::class]);
    configureInter();

    $this->postJson('/webhooks/phpay/inter', [['txid' => 'x', 'valor' => '1.00']])
        ->assertStatus(501)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'não assina'));

    Event::assertNotDispatched(WebhookEventReceived::class);
});

it('com unverified declarado, a rota lê a notificação do inter', function () {
    Event::fake([WebhookEventReceived::class]);
    configureInter();
    config()->set('phpay.webhooks.inter', ['unverified' => true]);

    /* o corpo do Inter é um array na raiz, não o {"pix": [...]} do padrão */
    $this->postJson('/webhooks/phpay/inter', [[
        'endToEndId' => 'E87654321202009091221dfghi123456',
        'txid'       => '971122d8f37211eaadc10242ac120002',
        'valor'      => '110.00',
        'horario'    => '2026-01-01T10:00:00Z',
    ]])->assertOk()->assertJson(['events' => 1]);

    Event::assertDispatched(
        WebhookEventReceived::class,
        fn (WebhookEventReceived $received): bool => $received->gateway === 'inter'
            && $received->event->type === EventType::PAID
            && $received->event->resourceId === '971122d8f37211eaadc10242ac120002'
    );
});

it('o comando lista o inter', function () {
    configureInter();

    $this->artisan('phpay:gateways')
        ->expectsOutputToContain('inter')
        ->assertSuccessful();
});
