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
     | pagarme, cielo, rede, abacatepay, inter.
     |
     */

    'default' => env('PHPAY_GATEWAY', Gateway::ASAAS->value),

    /*
     |--------------------------------------------------------------------------
     | Credenciais
     |--------------------------------------------------------------------------
     |
     | As chaves de cada gateway são as que ele exige — e elas não coincidem:
     | seis pedem uma credencial, quatro pedem duas. Mercado Pago, Pagar.me e
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
            'passphrase'  => env('EFI_CERTIFICATE_PASSPHRASE'),
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

        'inter' => [
            'clientId'     => env('INTER_CLIENT_ID'),
            'clientSecret' => env('INTER_CLIENT_SECRET'),
            'sandbox'      => env('INTER_SANDBOX', true),

            /*
             | Toda requisição do Inter é por mTLS, inclusive a do token.
             |
             | Ele entrega certificado e chave em DOIS arquivos (.crt + .key):
             | informe os dois e o pacote monta o par. A documentação do Inter
             | manda juntá-los com `openssl pkcs12 -export` antes de usar — não
             | precisa, mas se você já converteu, preencha só `certificate` com
             | o caminho do .p12.
             */
            'certificate' => env('INTER_CERTIFICATE'),
            'privateKey'  => env('INTER_PRIVATE_KEY'),
            'passphrase'  => env('INTER_CERTIFICATE_PASSPHRASE'),

            /*
             | O token do Inter é emitido POR ESCOPO, e `scope` é obrigatório na
             | requisição. Vazio aqui = a união do que as capacidades do gateway
             | usam, que é o que a maioria quer. Preencha só se a sua aplicação
             | tem permissão mais estreita — pedir escopo que ela não tem reprova
             | a requisição inteira do token.
             */
            'scopes' => env('INTER_SCOPES'),

            /*
             | Header `x-conta-corrente`. Só quando a aplicação está vinculada a
             | mais de uma conta corrente; vazio, o header não é mandado.
             */
            'account' => env('INTER_ACCOUNT'),
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
         | Efí, Inter, Pagar.me, Cielo e Rede NÃO assinam a notificação, cada um
         | pelo seu motivo (Efí e Inter usam mTLS; as outras indicam consultar a
         | transação de volta). Sem assinatura, a rota recusa a notificação — a
         | menos que você declare aqui que conhece o risco e confere de outra
         | forma.
         |
         | 'cielo' => ['unverified' => true],
         */

    ],

];
