<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Mail\AccountWillExpire;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->user->email)->send(new AccountWillExpire($this->user, $this->days));
    }
}
