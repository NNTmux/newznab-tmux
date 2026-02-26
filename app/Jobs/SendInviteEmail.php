<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SendInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInviteEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $email;

    private string $url;

    private \App\Models\User $user;

    /**
     * SendInviteEmail constructor.
     */
    public function __construct(string $email, \App\Models\User $user, string $url)
    {
        $this->email = $email;
        $this->user = $user;
        $this->url = $url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->email)->send(new SendInvite($this->user, $this->url));
    }
}
