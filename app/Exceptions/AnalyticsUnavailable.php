<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Google Analytics cannot answer, with a reason the admin can act on.
 *
 * The message is always one of the translated `admin.analytics.error.*`
 * strings: never Google's raw response, which can echo request details.
 */
class AnalyticsUnavailable extends RuntimeException
{
    /** This website is not connected to the MatBao gateway (no/invalid token). */
    public const NO_KEY = 'no_key';

    /** Measurement/property IDs not entered in Settings. */
    public const NOT_CONFIGURED = 'not_configured';

    /** The service account is not a user of the property. */
    public const NO_ACCESS = 'no_access';

    /** Token exchange refused the key. */
    public const BAD_KEY = 'bad_key';

    /** Google is down, slow or rate limiting. */
    public const GOOGLE_ERROR = 'google_error';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(__('admin.analytics.error.'.$reason));
    }
}
