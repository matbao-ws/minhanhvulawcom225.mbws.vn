<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

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
}
