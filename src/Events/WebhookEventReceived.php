<?php

namespace PHPay\Laravel\Events;

use PHPay\Webhooks\Event;

/**
 * a verified notification, already read.
 *
 * **one event class, not one per gateway and type.** Nine gateways times
 * twelve `EventType` cases would be 108 classes, and `EventType` exists
 * precisely so the listener can match:
 *
 * ```php
 * match ($received->event->type) {
 *     EventType::PAID     => $this->liberar($received->event->resourceId),
 *     EventType::REFUNDED => $this->estornar($received->event->resourceId),
 *     default             => null,
 * };
 * ```
 *
 * the `Event` is readonly and holds only arrays, strings and a
 * DateTimeImmutable, so a queued listener serializes it fine.
 */
class WebhookEventReceived
{
    /**
     * @param string $gateway the name in config, not the label
     * @param Event $event
     */
    public function __construct(
        public readonly string $gateway,
        public readonly Event $event,
    ) {
    }
}
