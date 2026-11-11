<?php

namespace App\Notifications;

use App\Support\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('shop.auth.reset_mail.subject', ['name' => Site::name()]))
            ->greeting(__('shop.auth.reset_mail.greeting', ['name' => $notifiable->firstName()]))
            ->line(__('shop.auth.reset_mail.line'))
            ->action(__('shop.auth.reset_mail.action'), $url)
            ->line(__('shop.auth.reset_mail.expire', ['count' => $expire]))
            ->line(__('shop.auth.reset_mail.ignore'))
            ->salutation(__('shop.auth.reset_mail.salutation', ['name' => Site::name()]));
    }
}
