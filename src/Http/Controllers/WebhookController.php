<?php

namespace PHPay\Laravel\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPay\Contracts\Capability;
use PHPay\Exceptions\ValidationException;
use PHPay\Laravel\Events\WebhookEventReceived;
use PHPay\Laravel\GatewayManager;

/**
 * reads a verified notification and dispatches one Laravel event per PHPay
 * event.
 *
 * the signature was already checked by the middleware; this only reads. One
 * notification can carry several events — the Efí Pix API sends an array of
 * received payments, PagBank sends an order with every charge — so this
 * dispatches in a loop rather than once.
 */
class WebhookController
{
    /**
     * @param GatewayManager $manager
     * @param Dispatcher $events
     */
    public function __construct(
        private readonly GatewayManager $manager,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * @param Request $request
     * @param string|null $gateway
     * @return JsonResponse
     */
    public function __invoke(Request $request, ?string $gateway = null): JsonResponse
    {
        $name     = $gateway ?? $this->manager->defaultName();
        $concrete = $this->manager->gateway($name);

        if (!$concrete->supports(Capability::WEBHOOK_EVENTS)) {
            return response()->json([
                'message' => sprintf(
                    'A biblioteca ainda não lê a notificação do %s, porque o formato dela não está confirmado. '
                    . 'Trate esta rota no seu próprio controller.',
                    $concrete->name()
                ),
            ], 501);
        }

        try {
            $parsed = $concrete->webhookEvents($request->getContent());
        } catch (ValidationException $exception) {
            /* corpo vazio ou não-JSON: integração, não evento desconhecido */
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        foreach ($parsed as $event) {
            $this->events->dispatch(new WebhookEventReceived($name, $event));
        }

        return response()->json(['events' => count($parsed)]);
    }
}
