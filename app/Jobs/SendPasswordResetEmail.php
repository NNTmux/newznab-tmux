<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\PasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \App\Models\User
     */
    private $user;

    /**
     * @var string
     */
    private $newPass;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $newPass)
    {
        $this->user = $user;
        $this->newPass = $newPass;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new PasswordReset($this->user, $this->newPass));
    }
}
