<?php

namespace App\Services\Balikos;

class PushNotificationService
{
    public function __construct(
        private readonly ExpoPushService $expo,
        private readonly FcmPushService $fcm
    ) {}

    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        return $this->fcm->sendToUser($userId, $title, $body, $data)
            + $this->expo->sendToUser($userId, $title, $body, $data);
    }
}
