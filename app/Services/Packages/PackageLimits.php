<?php

namespace App\Services\Packages;

use App\Exceptions\PackageLimitExceeded;
use App\Models\Banner;
use App\Models\Post;
use App\Models\Product;
use App\Services\AiGateway\GatewayClient;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\MailSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Quantity limits of the sold package.
 *
 * The internal tool is the only source: a limit changes when sales upgrade
 * the contract and update the tool, never from inside the core. Without a
 * package snapshot nothing is limited — the behaviour every shop had before
 * packages existed.
 *
 * Limits apply to superadmin too: support importing a catalogue past the
 * package is exactly what they exist to catch.
 *
 * The monthly mail quota is the exception: the MatBao relay on the gateway
 * counts and enforces it, where this website's code cannot reach it, so this
 * class only reads its figures.
 */
class PackageLimits
{
    public const DATA_UNIT = 'data_unit';

    public const BANNER = 'banner';

    public const EMAIL_MONTHLY = 'email_monthly';

    public const METRICS = [self::DATA_UNIT, self::BANNER, self::EMAIL_MONTHLY];

    public const EMAIL_USAGE_CACHE_KEY = 'packages.email_usage';

    /** Last refusal in this request, so importers can surface it once at the end. */
    private ?PackageLimitExceeded $lastExceeded = null;

    public function __construct(
        private readonly PackageSync $sync,
        private readonly GatewayClient $gateway,
    ) {}

    public function limit(string $metric): ?int
    {
        $value = $metric === self::EMAIL_MONTHLY
            ? ($this->emailUsage()['limit'] ?? null)
            : data_get($this->sync->snapshot(), 'limits.'.$metric);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function usage(string $metric): int
    {
        return match ($metric) {
            self::DATA_UNIT => Product::query()->count() + Post::query()->count(),
            self::BANNER => Banner::query()->count(),
            self::EMAIL_MONTHLY => (int) ($this->emailUsage()['used'] ?? 0),
            default => 0,
        };
    }

    public function remaining(string $metric): ?int
    {
        $limit = $this->limit($metric);

        return $limit === null ? null : max(0, $limit - $this->usage($metric));
    }

    /**
     * @throws PackageLimitExceeded
     */
    public function ensureCanCreate(string $metric, int $count = 1): void
    {
        $remaining = $this->remaining($metric);

        if ($remaining !== null && $remaining < $count) {
            throw $this->lastExceeded = new PackageLimitExceeded($metric, (int) $this->limit($metric));
        }
    }

    public function lastExceeded(): ?PackageLimitExceeded
    {
        return $this->lastExceeded;
    }

    /**
     * @return array<string, array{limit: int|null, usage: int}>
     */
    public function summary(): array
    {
        $summary = [];

        foreach (self::METRICS as $metric) {
            $summary[$metric] = [
                'limit' => $this->limit($metric),
                'usage' => $this->usage($metric),
            ];
        }

        return $summary;
    }

    /**
     * The relay's figures for this month, cached five minutes. Empty when the
     * website does not use the relay or the gateway cannot answer.
     *
     * @return array{used?: int, limit?: int}
     */
    private function emailUsage(): array
    {
        // A shop sending through its own SMTP is not on the relay's quota.
        if (app(MailSettings::class)->source() !== MailSettings::SOURCE_REMOTE) {
            return [];
        }

        try {
            return Cache::remember(self::EMAIL_USAGE_CACHE_KEY, 300, fn () => (array) $this->gateway->get('v1/mail/usage')['usage']);
        } catch (GatewayUnavailable) {
            return [];
        }
    }
}
