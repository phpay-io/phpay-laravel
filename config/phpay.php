<?php

use PHPay\Support\Gateway;

return [

    /*
     |--------------------------------------------------------------------------
     | Gateway padrão
     |--------------------------------------------------------------------------
     |
     | O nome que `Phpay::charge()` usa quando você não pede outro. Tem que ser
     | um dos que o PHPay conhece: asaas, woovi, efi, mercadopago, pagbank,
     | pagarme, cielo, rede, abacatepay.
     |
     */

    'default' => env('PHPAY_GATEWAY', Gateway::ASAAS->value),

    /*
     |--------------------------------------------------------------------------
     | Credenciais
     |--------------------------------------------------------------------------
     |
     | As chaves de cada gateway são as que ele exige — e elas não coincidem:
     | seis pedem uma credencial, três pedem duas. Mercado Pago, Pagar.me e
     | AbacatePay NÃO aceitam `sandbox`, porque o ambiente deles vem do prefixo
     | da credencial; mandar a chave ali levanta exceção em vez de ser ignorada.
     |
     | Só o gateway que você usa precisa estar preenchido.
     |
     */

    'gateways' => [

        'asaas' => [
            'token'   => env('ASAAS_TOKEN'),
            'sandbox' => env('ASAAS_SANDBOX', true),
        ],

        'woovi' => [
            'appId'   => env('WOOVI_APP_ID'),
            'sandbox' => env('WOOVI_SANDBOX', true),
        ],

        'efi' => [
            'clientId'     => env('EFI_CLIENT_ID'),
            'clientSecret' => env('EFI_CLIENT_SECRET'),
            'sandbox'      => env('EFI_SANDBOX', true),

            // a API Pix da Efí é só por mTLS: caminho do .p12 ou .pem
            'certificate' => env('EFI_CERTIFICATE'),
        ],

        'mercadopago' => [
            'accessToken' => env('MERCADOPAGO_ACCESS_TOKEN'),
        ],

        'pagbank' => [
            'token'   => env('PAGBANK_TOKEN'),
            'sandbox' => env('PAGBANK_SANDBOX', true),
        ],

        'pagarme' => [
            'secretKey' => env('PAGARME_SECRET_KEY'),
        ],

        'cielo' => [
            'merchantId'  => env('CIELO_MERCHANT_ID'),
            'merchantKey' => env('CIELO_MERCHANT_KEY'),
            'sandbox'     => env('CIELO_SANDBOX', true),
        ],

        'rede' => [
            'affiliation' => env('REDE_AFFILIATION'),
            'secret'      => env('REDE_SECRET'),
            'sandbox'     => env('REDE_SANDBOX', true),
        ],

        'abacatepay' => [
            'token' => env('ABACATEPAY_TOKEN'),
        ],

    ],

    /*
     |--------------------------------------------------------------------------
     | Webhooks
     |--------------------------------------------------------------------------
     |
     | Com `route` preenchida, o pacote registra uma rota que verifica a
     | assinatura, lê a notificação e despacha um WebhookEventReceived por
     | evento. Deixe `null` para cuidar disso no seu próprio controller.
     |
     | O `{gateway}` da rota é o nome do gateway, e cada um tem o seu segredo.
     |
     */

    'webhooks' => [

        'route'      => env('PHPAY_WEBHOOK_ROUTE', '/webhooks/phpay/{gateway}'),
        'middleware' => ['api'],

        'asaas'       => ['secret' => env('ASAAS_WEBHOOK_TOKEN')],
        'woovi'       => ['secret' => env('WOOVI_WEBHOOK_SECRET')],
        'mercadopago' => ['secret' => env('MERCADOPAGO_WEBHOOK_SECRET')],
        'pagbank'     => ['secret' => env('PAGBANK_WEBHOOK_TOKEN')],

        'abacatepay' => [
            'secret'       => env('ABACATEPAY_WEBHOOK_SECRET'),
            'signatureKey' => env('ABACATEPAY_WEBHOOK_SIGNATURE_KEY'),
        ],

        /*
         | Efí, Pagar.me, Cielo e Rede NÃO assinam a notificação, cada um pelo
         | seu motivo (a Efí usa mTLS; as outras indicam consultar a transação
         | de volta). Sem assinatura, a rota recusa a notificação — a menos que
         | você declare aqui que conhece o risco e confere de outra forma.
         |
         | 'cielo' => ['unverified' => true],
         */

    ],

];
