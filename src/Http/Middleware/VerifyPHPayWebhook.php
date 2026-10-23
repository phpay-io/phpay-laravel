<?php

namespace PHPay\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPay\Contracts\Capability;
use PHPay\Exceptions\ValidationException;
use PHPay\Laravel\GatewayManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * refuses a notification that was not proved to come from the gateway.
 *
 * **it never passes in silence.** Four of the nine gateways do not sign, each
 * for its own reason, and registering a webhook route for one of them without
 * saying so is how a forged payment confirmation gets in — which is the risk
 * the signature capability exists to close. So a gateway that cannot be
 * verified is refused with `501`, unless the application declares in config
 * that it knows and checks another way:
 *
 * ```php
 * 'cielo' => ['unverified' => true],
 * ```
 */
class VerifyPHPayWebhook
{
    /**
     * @param GatewayManager $manager
     */
    public function __construct(
        private readonly GatewayManager $manager
    ) {
    }

    /**
     * @param Request $request
     * @param Closure(Request): Response $next
     * @param string|null $gateway name, when the route does not carry it
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $gateway = null): Response
    {
        $name     = $gateway ?? $this->gatewayOf($request);
        $webhook  = $this->manager->webhookConfig($name);
        $concrete = $this->manager->gateway($name);

        if (!$concrete->supports(Capability::WEBHOOK_SIGNATURE)) {
            return ($webhook['unverified'] ?? false) === true
                ? $next($request)
                : $this->refuse(
                    501,
                    sprintf(
                        'O %s não assina a notificação, então ela não pode ser verificada. '
                        . 'Confira a transação de volta na API e, se for isso que você faz, '
                        . 'declare "unverified" => true na configuração de webhooks dele.',
                        $concrete->name()
                    )
                );
        }

        $secret = $webhook['secret'] ?? null;

        if (!is_string($secret) || trim($secret) === '') {
            return $this->refuse(
                500,
                sprintf('Falta o segredo do webhook do %s em config/phpay.php.', $concrete->name())
            );
        }

        try {
            $ok = $this->verified($request, $name, $secret);
        } catch (ValidationException $exception) {
            /*
             | o core separa "assinatura não bate" (false) de "não chegou
             | assinatura para verificar" (exceção), e a segunda costuma ser
             | proxy que removeu o header. Para quem chama, as duas são
             | requisição não autenticada: 401, com a causa no corpo, em vez de
             | 500 — que faria o gateway repetir contra um endpoint quebrado.
             */
            return $this->refuse(401, $exception->getMessage());
        }

        return $ok ? $next($request) : $this->refuse(401, 'Assinatura inválida.');
    }

    /**
     * whether the signature checks out.
     *
     * three gateways need more than the body and the headers, and the
     * verifier of each says so by having the setter: Mercado Pago folds the
     * `data.id` of the query into the manifest, AbacatePay keeps a secret in
     * the query, and Woovi accepts public keys instead of fetching them.
     *
     * @param Request $request
     * @param string $name
     * @param string $secret
     * @return bool
     */
    private function verified(Request $request, string $name, string $secret): bool
    {
        $verifier = $this->manager->gateway($name)->webhookSignature();

        $verifier->setSecret($secret)
            ->setPayload($request->getContent())
            ->setHeaders($request->headers->all());

        $query = $request->query();
        $query = is_array($query) ? $query : [];

        if (method_exists($verifier, 'setDataId')) {
            $dataId = $query['data.id'] ?? null;
            $verifier->setDataId(is_string($dataId) ? $dataId : null);
        }

        if (method_exists($verifier, 'setQuery')) {
            $verifier->setQuery($query);
        }

        $webhook = $this->manager->webhookConfig($name);

        if (method_exists($verifier, 'setSignatureKey')) {
            $key = $webhook['signatureKey'] ?? null;

            if (is_string($key) && trim($key) !== '') {
                $verifier->setSignatureKey($key);
            }
        }

        if (method_exists($verifier, 'setPublicKeys')) {
            $keys = $webhook['publicKeys'] ?? null;

            if (is_array($keys) && $keys !== []) {
                $verifier->setPublicKeys($keys);
            }
        }

        return (bool) $verifier->verify();
    }

    /**
     * the gateway named in the route, falling back to the default.
     *
     * @param Request $request
     * @return string
     */
    private function gatewayOf(Request $request): string
    {
        $name = $request->route('gateway');

        return is_string($name) && trim($name) !== '' ? $name : $this->manager->defaultName();
    }

    /**
     * a refusal the gateway's retry can read, and a human can too.
     *
     * @param int $status
     * @param string $reason
     * @return Response
     */
    private function refuse(int $status, string $reason): Response
    {
        return response()->json(['message' => $reason], $status);
    }
}
