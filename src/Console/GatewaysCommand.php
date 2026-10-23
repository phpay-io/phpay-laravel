<?php

namespace PHPay\Laravel\Console;

use Illuminate\Console\Command;
use PHPay\Contracts\Capability;
use PHPay\Laravel\GatewayManager;
use PHPay\Support\Gateway;
use Throwable;

/**
 * what is configured, and what each one can do.
 *
 * the capabilities do not coincide, and the one that matters most here is the
 * webhook pair: there are gateways the library can read but not authenticate,
 * and the opposite. Seeing that in a table beats finding out in production.
 */
class GatewaysCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'phpay:gateways';

    /**
     * @var string
     */
    protected $description = 'Lista os gateways configurados e as capacidades de cada um';

    /**
     * @param GatewayManager $manager
     * @return int
     */
    public function handle(GatewayManager $manager): int
    {
        $default = $manager->defaultName();
        $rows    = [];

        foreach ($manager->configured() as $name) {
            $rows[] = $this->row($manager, $name, $default);
        }

        if ($rows === []) {
            $this->warn('Nenhum gateway em config/phpay.php. Publique o config com: php artisan vendor:publish --tag=phpay-config');

            return self::FAILURE;
        }

        $this->table(['gateway', 'credenciais', 'cobranças', 'estornos', 'assina webhook', 'lê webhook'], $rows);

        return self::SUCCESS;
    }

    /**
     * one line of the table.
     *
     * a gateway with no credentials filled in is shown as such instead of
     * taking the command down: listing what is missing is the point.
     *
     * @param GatewayManager $manager
     * @param string $name
     * @param string $default
     * @return array<int, string>
     */
    private function row(GatewayManager $manager, string $name, string $default): array
    {
        $label = $name === $default ? "{$name} (padrão)" : $name;

        try {
            $gateway = $manager->gateway($name);
        } catch (Throwable $exception) {
            return [$label, '<fg=red>incompleta</>', '—', '—', '—', '—'];
        }

        return [
            $label,
            '<fg=green>ok</>',
            $this->mark($gateway->supports(Capability::CHARGES)),
            $this->mark($gateway->supports(Capability::REFUNDS)),
            $this->mark($gateway->supports(Capability::WEBHOOK_SIGNATURE)),
            $this->mark($gateway->supports(Capability::WEBHOOK_EVENTS)),
        ];
    }

    /**
     * @param bool $supported
     * @return string
     */
    private function mark(bool $supported): string
    {
        return $supported ? '<fg=green>sim</>' : '—';
    }
}
