<?php

it('lista os gateways configurados com as capacidades de cada um', function () {
    $this->artisan('phpay:gateways')
        ->assertSuccessful();
});

it('marca como incompleta a configuração que não dá para construir', function () {
    config()->set('phpay.gateways', ['asaas' => ['token' => null]]);

    /* o comando não cai: listar o que falta é o ponto dele */
    $this->artisan('phpay:gateways')->assertSuccessful();
});

it('avisa quando não há gateway configurado', function () {
    config()->set('phpay.gateways', []);

    $this->artisan('phpay:gateways')->assertFailed();
});
