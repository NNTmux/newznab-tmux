<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AccountWillExpire;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAccountWillExpireEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $days;

    private \App\Models\User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\User $user, int $days)
    {
        $this->user = $user;
        $this->days = $days;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new AccountWillExpire($this->user, $this->days));
    }
}
