<?php

namespace App\Jobs;

use App\Mail\SendInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInviteEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;

    private $userId;

    private $url;

    /**
     * Create a new job instance.
     *
     * @param $email
     * @param $userId
     * @param $url
     */
    public function __construct($email, $userId, $url)
    {
        $this->email = $email;
        $this->userId = $userId;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Mail::to($this->email)->send(new SendInvite($this->userId, $this->url));
    }
}
