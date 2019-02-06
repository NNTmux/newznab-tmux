<?php

namespace App\Jobs;

use App\Mail\PasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    private $email;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var string
     */
    private $newPass;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\User $user
     * @param string $newPass
     */
    public function __construct($user, $newPass)
    {
        $this->email = $user->email;
        $this->userId = $user->id;
        $this->newPass = $newPass;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send(new PasswordReset($this->userId, $this->newPass));
    }
}
