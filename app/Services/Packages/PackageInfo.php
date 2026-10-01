<?php

namespace App\Services\Packages;

use Illuminate\Support\Facades\Http;

/**
 * Reads the package a project was sold with from the internal tool.
 *
 * Only the transport lives here; PackageSync decides what the answer means for
 * the shop. Kept separate so the sync logic can be tested without HTTP.
 */
class PackageInfo
{
    public const OK = 'ok';

    /** The tool answered but does not know this domain. */
    public const NOT_FOUND = 'not_found';

    /** No answer, a non-JSON answer, or a malformed one. */
    public const UNREACHABLE = 'unreachable';

    public function configured(): bool
    {
        return filled(config('packages.domain')) && filled(config('packages.info_url'));
    }

    /**
     * @return array{status: string, package: array<string, mixed>|null}
     */
    public function fetch(): array
    {
        if (! $this->configured()) {
            return ['status' => self::UNREACHABLE, 'package' => null];
        }

        $url = rtrim((string) config('packages.info_url'), '/').'/'.rawurlencode((string) config('packages.domain'));

        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)->get($url);
        } catch (\Throwable) {
            return ['status' => self::UNREACHABLE, 'package' => null];
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return ['status' => self::UNREACHABLE, 'package' => null];
        }

        if ((int) $response->json('retCode', -1) !== 0) {
            return ['status' => self::NOT_FOUND, 'package' => null];
        }

        $data = $response->json('data');

        if (! is_array($data) || blank($data['code'] ?? null)) {
            return ['status' => self::UNREACHABLE, 'package' => null];
        }

        return ['status' => self::OK, 'package' => $this->normalize($data)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $limits = [];
        foreach (config('packages.limits', []) as $metric => $field) {
            $value = $data[$field] ?? null;
            $limits[$metric] = is_numeric($value) && (int) $value > 0 ? (int) $value : null;
        }

        return [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => (string) ($data['packageName'] ?? $data['code']),
            'limits' => $limits,
            // Shown to support, not enforced by the core.
            'details' => array_filter([
                'interface_type' => $data['interfaceType'] ?? null,
                'layouts' => $data['numberLayouts'] ?? null,
                'data' => $data['numberData'] ?? null,
                'primary_colors' => $data['numberPrimaryColor'] ?? null,
                'page_layout_changes' => $data['numberChangePageLayout'] ?? null,
            ], fn ($value) => $value !== null),
        ];
    }
}
