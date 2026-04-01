<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ServiceIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IncidentDetected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly ServiceIncident $incident,
        private readonly bool $resolved = false
    ) {}

    public function build(): static
    {
        $site = config('app.name');
        $status = $this->resolved ? 'Resolved' : 'Detected';
        $services = $this->incident->services->pluck('name')->join(', ');

        return $this->from(config('mail.from.address'))
            ->subject("[{$site}] Service Incident {$status}: {$services}")
            ->view('emails.incidentDetected')
            ->with([
                'incident' => $this->incident,
                'services' => $services,
                'resolved' => $this->resolved,
                'site' => $site,
                'statusUrl' => url('/admin/status'),
            ]);
    }
}
