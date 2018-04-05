<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Http\Requests\ContactFormRequest;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InboxMessage extends Notification
{
    use Queueable;

    protected $message;

    /**
     * InboxMessage constructor.
     *
     * @param \App\Http\Requests\ContactFormRequest $message
     */
    public function __construct(contactFormRequest $message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(env('ADMIN_USER').', you got a new message!')
            ->greeting(' ')
            ->salutation(' ')
            ->from($this->message->email, $this->message->name)
            ->line($this->message->message);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
