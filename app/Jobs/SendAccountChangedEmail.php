<?php

namespace App\Jobs;

use App\Mail\AccountChange;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class SendAccountChangedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;

    private $id;

    /**
     * Create a new job instance.
     *
     * @param $email
     * @param $id
     */
    public function __construct($email, $id)
    {
        $this->email = $email;
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send(new AccountChange($this->id));
    }
}
