<?php

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

    private $days;

    private $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $days)
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
