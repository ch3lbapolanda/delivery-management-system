<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public int $orderId,
        public string $orderNumber,
        public array $data = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'data' => $this->data,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(
            $this->toDatabase($notifiable)
        );
    }
}
