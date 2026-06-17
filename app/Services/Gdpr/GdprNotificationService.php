<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

use App\Mail\GdprRequestStatusMail;
use App\Models\GdprRequest;
use Illuminate\Support\Facades\Mail;

class GdprNotificationService
{
    public function requestSubmitted(GdprRequest $gdprRequest): void
    {
        $this->sendToRequester($gdprRequest, 'submitted');

        if ($gdprRequest->type === GdprRequest::TYPE_ERASURE) {
            $this->sendToAdmin($gdprRequest, 'submitted');
        }
    }

    public function exportReady(GdprRequest $gdprRequest): void
    {
        $this->sendToRequester($gdprRequest, 'export_ready');
    }

    public function erasureCompleted(GdprRequest $gdprRequest): void
    {
        $this->sendToRequester($gdprRequest, 'erasure_completed');
    }

    public function requestRejected(GdprRequest $gdprRequest): void
    {
        $this->sendToRequester($gdprRequest, 'rejected');
    }

    private function sendToRequester(GdprRequest $gdprRequest, string $event): void
    {
        $email = trim((string) $gdprRequest->requester_email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::to($email)->send(new GdprRequestStatusMail($gdprRequest, $event));
    }

    private function sendToAdmin(GdprRequest $gdprRequest, string $event): void
    {
        $email = trim((string) config('mail.from.address'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::to($email)->send(new GdprRequestStatusMail($gdprRequest, $event, adminCopy: true));
    }
}
