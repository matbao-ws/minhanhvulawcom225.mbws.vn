<?php

namespace App\Services\AiGateway;

use RuntimeException;

/** The gateway refused or could not be reached; `reason` is its stable code. */
class GatewayUnavailable extends RuntimeException
{
    /** MATBAO_AI_URL / MATBAO_AI_TOKEN missing on this website. */
    public const NOT_CONFIGURED = 'not_configured';

    /** Network failure, timeout or a response that is not the gateway's JSON. */
    public const UNREACHABLE = 'unreachable';

    /**
     * @param  array<string, list<string>>  $errors  field errors on invalid_input
     */
    public function __construct(public readonly string $reason, public readonly array $errors = [])
    {
        parent::__construct('MatBao gateway: '.$reason);
    }
}
