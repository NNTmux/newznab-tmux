<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasBrandedSubject;
use App\Models\GdprRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class GdprRequestStatusMail extends Mailable
{
    use HasBrandedSubject, Queueable, SerializesModels;

    public string $site;

    public string $requestType;

    public string $status;

    public string $heading;

    public string $body;

    public string $requester;

    public string $requestedAt;

    public ?string $completedAt;

    public ?string $adminNotes;

    public ?string $actionUrl;

    public ?string $actionText;

    public ?string $preheader;

    private string $siteEmail;

    private string $subjectLine;

    public function __construct(GdprRequest $gdprRequest, string $event, bool $adminCopy = false)
    {
        $this->siteEmail = (string) config('mail.from.address');
        $this->site = (string) config('app.name');
        $this->requestType = ucfirst(str_replace('_', ' ', $gdprRequest->type));
        $this->status = ucfirst(str_replace('_', ' ', $gdprRequest->status));
        $this->requester = trim(($gdprRequest->requester_username ?: 'Unknown user').' <'.($gdprRequest->requester_email ?: 'unknown email').'>');
        $this->requestedAt = $gdprRequest->created_at === null ? 'N/A' : Carbon::parse($gdprRequest->created_at)->format('Y-m-d H:i T');
        $this->completedAt = $gdprRequest->completed_at === null ? null : Carbon::parse($gdprRequest->completed_at)->format('Y-m-d H:i T');
        $this->adminNotes = $gdprRequest->admin_notes;

        [$this->subjectLine, $this->heading, $this->body, $this->actionText, $this->actionUrl] = $this->contentFor($gdprRequest, $event, $adminCopy);
        $this->preheader = $this->body;
    }

    /**
     * @throws \Exception
     */
    public function build(): static
    {
        return $this->from($this->siteEmail)
            ->brandedSubject($this->subjectLine)
            ->markdown('emails.markdown.gdprRequestStatus', [
                'site' => $this->site,
                'requestType' => $this->requestType,
                'status' => $this->status,
                'heading' => $this->heading,
                'body' => $this->body,
                'requester' => $this->requester,
                'requestedAt' => $this->requestedAt,
                'completedAt' => $this->completedAt,
                'adminNotes' => $this->adminNotes,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText,
                'preheader' => $this->preheader,
            ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}
     */
    private function contentFor(GdprRequest $gdprRequest, string $event, bool $adminCopy): array
    {
        if ($adminCopy) {
            return [
                'New GDPR '.$this->requestType.' request',
                'New GDPR request submitted',
                'A GDPR '.$this->requestType.' request was submitted and may require administrator review.',
                'Review request',
                $this->safeRoute('admin.gdpr-requests.show', [$gdprRequest]),
            ];
        }

        return match ($event) {
            'submitted' => [
                'GDPR '.$this->requestType.' request received',
                'We received your GDPR request',
                'Your GDPR '.$this->requestType.' request has been received and recorded.',
                'Open Privacy Center',
                $this->safeRoute('privacy-center.index'),
            ],
            'export_ready' => [
                'Your GDPR data export is ready',
                'Your data export is ready',
                'Your GDPR data export has been generated and is available for download until its listed expiry time.',
                'Download export',
                $gdprRequest->isDownloadableExport()
                    ? $this->safeRoute('privacy-center.export.download', [$gdprRequest])
                    : $this->safeRoute('privacy-center.index'),
            ],
            'erasure_completed' => [
                'Your GDPR erasure request is complete',
                'Your account erasure is complete',
                'Your GDPR erasure request has been completed. Account data was removed or anonymized, and retained payment/audit records were anonymized or minimized where practical.',
                null,
                null,
            ],
            'rejected' => [
                'Your GDPR request was rejected',
                'Your GDPR request was rejected',
                'Your GDPR '.$this->requestType.' request was reviewed and rejected. See the notes below for the reason provided by the administrator.',
                'Open Privacy Center',
                $this->safeRoute('privacy-center.index'),
            ],
            default => [
                'GDPR request update',
                'GDPR request update',
                'There has been an update to your GDPR '.$this->requestType.' request.',
                'Open Privacy Center',
                $this->safeRoute('privacy-center.index'),
            ],
        };
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    private function safeRoute(string $name, array $parameters = []): ?string
    {
        try {
            return route($name, $parameters);
        } catch (Throwable) {
            return null;
        }
    }
}
