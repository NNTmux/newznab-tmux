<?php

namespace App\Jobs;

use App\Mail\PasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;

    private $userId;

    private $newPass;

    /**
     * Create a new job instance.
     *
     * @param $email
     * @param $userId
     * @param $newPass
     */
    public function __construct($email, $userId, $newPass)
    {
        $this->email = $email;
        $this->userId = $userId;
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
