<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Charge\Charge as AsaasCharge;
use PHPay\Contracts\Capability;
use PHPay\Exceptions\ValidationException;
use PHPay\Laravel\Exceptions\GatewayNotConfigured;
use PHPay\Laravel\Facades\Phpay;
use PHPay\Laravel\GatewayManager;
use PHPay\PHPay as CorePHPay;

it('resolve o gateway padrão da configuração', function () {
    $manager = app(GatewayManager::class);

    expect($manager->defaultName())->toBe('asaas')
        ->and($manager->gateway()->name())->toBe('Asaas');
});

it('resolve outro gateway pelo nome', function () {
    $manager = app(GatewayManager::class);

    expect($manager->gateway('woovi')->name())->toBe('Woovi')
        ->and($manager->gateway('cielo')->name())->toBe('Cielo');
});

it('reaproveita a instância do mesmo nome dentro da requisição', function () {
    $manager = app(GatewayManager::class);

    /* o gateway guarda credencial e, na Efí, token trocado: reconstruir a cada
       chamada jogaria fora o cache de token */
    expect($manager->gateway('asaas'))->toBe($manager->gateway('asaas'));

    $antes = $manager->gateway('asaas');
    $manager->forget();

    expect($manager->gateway('asaas'))->not->toBe($antes);
});

it('tolera caixa e espaço no nome', function () {
    $manager = app(GatewayManager::class);

    expect($manager->gateway(' ASAAS ')->name())->toBe('Asaas');
});

it('diz o que está configurado quando o gateway não está', function () {
    $manager = app(GatewayManager::class);

    expect(fn () => $manager->gateway('pagarme'))
        ->toThrow(GatewayNotConfigured::class, 'não tem configuração em config/phpay.php');

    expect(fn () => $manager->gateway('pagarme'))
        ->toThrow(GatewayNotConfigured::class, 'asaas, woovi, cielo, mercadopago, incompleto');
});

it('descarta credencial nula, para a mensagem útil ser a do core', function () {
    $manager = app(GatewayManager::class);

    /*
     | `env()` não definido chega como null. Repassar null faria o registry
     | reclamar de tipo; descartando, a reclamação é "informe a chave", que é
     | a que diz o que fazer.
     */
    expect(fn () => $manager->gateway('incompleto'))
        ->toThrow(ValidationException::class, 'Não existe gateway "incompleto"');
});

it('recusa nome que não é gateway do phpay', function () {
    $app = app();
    $app['config']->set('phpay.gateways.xpto', ['token' => 'x']);

    expect(fn () => app(GatewayManager::class)->gateway('xpto'))
        ->toThrow(ValidationException::class, 'Não existe gateway "xpto"');
});

it('lista o que está configurado', function () {
    expect(app(GatewayManager::class)->configured())
        ->toBe(['asaas', 'woovi', 'cielo', 'mercadopago', 'incompleto']);
});

it('expõe o manager pela facade', function () {
    expect(Phpay::gateway()->name())->toBe('Asaas')
        ->and(Phpay::gateway('woovi')->name())->toBe('Woovi')
        ->and(Phpay::defaultName())->toBe('asaas');
});

it('delega capacidade e recurso pela facade, até o gateway', function () {
    expect(Phpay::gateway()->supports(Capability::CHARGES))->toBeTrue()
        ->and(Phpay::gateway()->charge())->toBeInstanceOf(AsaasCharge::class);
});

it('injeta o PHPay do core resolvendo o gateway padrão', function () {
    /* quem só usa um gateway injeta a classe do core e não vê o manager */
    $phpay = app(CorePHPay::class);

    expect($phpay)->toBeInstanceOf(CorePHPay::class)
        ->and($phpay->name())->toBe('Asaas');
});

it('não faz chamada de rede ao resolver', function () {
    /* se resolvesse pedindo token, um app com config errada quebraria no boot */
    $manager = app(GatewayManager::class);

    expect($manager->gateway('asaas'))->toBeInstanceOf(CorePHPay::class)
        ->and(app(GatewayManager::class)->gateway('mercadopago')->name())->toBe('Mercado Pago');
});

it('o gateway resolvido é o concreto por dentro', function () {
    $manager = app(GatewayManager::class);

    $property = new ReflectionProperty(CorePHPay::class, 'gateway');

    expect($property->getValue($manager->gateway('asaas')))->toBeInstanceOf(AsaasGateway::class);
});

it('encaminha para o gateway padrão o que o manager não tem', function () {
    /*
     | o README e a lista @method da facade anunciam Phpay::charge() desde o
     | primeiro commit, e isso levantava "Call to undefined method": o manager
     | não tinha o método nem encaminhava. Os testes do modo falso acharam.
     */
    expect(Phpay::charge())->toBeInstanceOf(AsaasCharge::class)
        ->and(Phpay::name())->toBe('Asaas')
        ->and(Phpay::supports(Capability::CHARGES))->toBeTrue();
});

it('diz quem não tem o método, quando ninguém tem', function () {
    expect(fn () => Phpay::metodoQueNaoExiste())
        ->toThrow(BadMethodCallException::class, 'nem o gateway padrão (Asaas)');
});
