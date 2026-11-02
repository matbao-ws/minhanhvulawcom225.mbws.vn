<?php

namespace App\Notifications;

use App\Services\EmailAppearance;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password-reset mail, sent from the queue with retries. Mail goes through
 * the MatBao relay; a brief gateway hiccup must delay the link, not turn the
 * "forgot password" form into an error page. The reset URL builder registered
 * on ResetPassword (AppServiceProvider) still applies.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    /**
     * The shop's own reset email (Quản trị › Giao diện email) instead of Laravel's default. The link
     * still comes from the URL builder registered on ResetPassword (customer or admin page).
     */
    public function toMail($notifiable): MailMessage
    {
        $appearance = app(EmailAppearance::class);
        $look = $appearance->look();
        $vars = ['ten_shop' => $look['shop']];
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject($appearance->text('reset_password', 'subject', $vars))
            ->view('emails.reset-password', [
                'look' => $look,
                'heading' => $appearance->text('reset_password', 'heading', $vars),
                'intro' => $appearance->text('reset_password', 'intro', $vars),
                'account' => (string) $notifiable->getEmailForPasswordReset(),
                'url' => $url,
                'minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                'preheader' => __('emails.ui.reset_expires', ['minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)]),
                'footer' => __('emails.ui.footer_admin', ['shop' => $look['shop']]),
            ]);
    }
}
