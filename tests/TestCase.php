<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PHPay\Laravel\PHPayServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param mixed $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PHPayServiceProvider::class];
    }

    /**
     * @param mixed $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Phpay' => \PHPay\Laravel\Facades\Phpay::class];
    }

    /**
     * configuração mínima: credenciais falsas, e nenhuma rede.
     *
     * @param mixed $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('phpay.default', 'asaas');
        $app['config']->set('phpay.gateways', [
            'asaas'       => ['token' => 'fake-token', 'sandbox' => true],
            'woovi'       => ['appId' => 'fake-app-id', 'sandbox' => true],
            'cielo'       => ['merchantId' => 'fake-id', 'merchantKey' => 'fake-key', 'sandbox' => true],
            'mercadopago' => ['accessToken' => 'TEST-fake'],
            'incompleto'  => ['token' => null],
        ]);
        $app['config']->set('phpay.webhooks', [
            'route'      => '/webhooks/phpay/{gateway}',
            'middleware' => [],
            'asaas'      => ['secret' => 'segredo-do-webhook'],
        ]);
    }
}
