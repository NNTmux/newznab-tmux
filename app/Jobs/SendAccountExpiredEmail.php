<?php

namespace App\Jobs;

use App\Mail\AccountExpired;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class SendAccountExpiredEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;

    private $userId;

    /**
     * Create a new job instance.
     *
     * @param $email
     * @param $userId
     */
    public function __construct($email, $userId)
    {
        $this->email = $email;
        $this->userId = $userId;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send(new AccountExpired($this->userId));
    }
}
