<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\ForgottenPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordForgottenEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $resetLink;

    /**
     * @var \App\Models\User
     */
    private $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, $resetLink)
    {
        $this->user = $user;
        $this->resetLink = $resetLink;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new ForgottenPassword($this->resetLink));
    }
}
