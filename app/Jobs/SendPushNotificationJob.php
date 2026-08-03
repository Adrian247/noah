<?php

namespace App\Jobs;

use App\Services\Notifications\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(PushNotifier $push): void
    {
        $push->deliver($this->userIds, $this->title, $this->body, $this->data);
    }
}
