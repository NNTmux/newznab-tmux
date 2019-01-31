<?php

namespace App\Jobs;

use App\Mail\ForgottenPassword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordForgottenEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;

    private $resetLink;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\User $user
     * @param $resetLink
     */
    public function __construct(User $user, $resetLink)
    {
        $this->email = $user->email;
        $this->resetLink = $resetLink;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send(new ForgottenPassword($this->resetLink));
    }
}
