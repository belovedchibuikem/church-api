<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Uri;

class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    protected function resetUrl($notifiable): string
    {
        return (string) Uri::of((string) config('api.browser.password_reset_url'))
            ->withQuery([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
    }
}
