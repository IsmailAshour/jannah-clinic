<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SecurityChanged extends Notification
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
