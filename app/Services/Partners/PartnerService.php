<?php

namespace App\Services\Partners;

use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Services\CloudinaryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Admin writes of partner logos and their groups (admin → Đối tác). Storefront reads go through
 * {@see PartnerQueryService}.
 *
 * Logos come from the media library (or an upload into it), so deleting a partner never deletes
 * the picture: another record may use the same file.
 */
class PartnerService
{
    /** Uploaded logos land beside brand logos in the media library. */
    public const MEDIA_FOLDER = 'brands';

    public function __construct(private readonly CloudinaryService $cloudinary) {}

    /** @param  array<string, mixed>  $data  validated PartnerRequest data */
    public function create(array $data): Partner
    {
        $groupId = (int) $data['partner_group_id'];

        return Partner::query()->create([
            'partner_group_id' => $groupId,
            'name' => trim((string) $data['name']),
            'logo_path' => $this->logo($data),
            'url' => $this->nullableString($data['url'] ?? null),
            // New logos join the end of their group's strip.
            'sort_order' => (int) (Partner::query()->where('partner_group_id', $groupId)->max('sort_order') ?? -1) + 1,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
    }

    /** @param  array<string, mixed>  $data  validated PartnerRequest data */
    public function update(Partner $partner, array $data): Partner
    {
        $groupId = (int) $data['partner_group_id'];
        $movedGroup = $groupId !== $partner->partner_group_id;

        $partner->update([
            'partner_group_id' => $groupId,
            'name' => trim((string) $data['name']),
            // Keep the stored (relative) value when no new picture was chosen.
            'logo_path' => $this->logo($data) ?? $partner->getRawOriginal('logo_path'),
            'url' => $this->nullableString($data['url'] ?? null),
            'sort_order' => $movedGroup
                ? (int) (Partner::query()->where('partner_group_id', $groupId)->max('sort_order') ?? -1) + 1
                : $partner->sort_order,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return $partner->refresh();
    }

    /** @param  array<int, int|string>  $ids  in their new order */
    public function reorder(array $ids, int $startOrder = 0): void
    {
        foreach (array_values($ids) as $index => $id) {
            Partner::query()->whereKey($id)->update(['sort_order' => $startOrder + $index]);
        }
    }

    /**
     * @param  array<string, mixed>  $data  validated PartnerGroupRequest data
     */
    public function createGroup(array $data): PartnerGroup
    {
        $name = trim((string) $data['name']);
        $key = $this->nullableString($data['key'] ?? null);

        return PartnerGroup::query()->create([
            'key' => $key ?? $this->uniqueKey($name),
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? ((PartnerGroup::query()->max('sort_order') ?? -1) + 1)),
        ]);
    }

    /**
     * The key is never touched: themes ask for the group by it.
     *
     * @param  array<string, mixed>  $data  validated PartnerGroupRequest data
     */
    public function updateGroup(PartnerGroup $group, array $data): PartnerGroup
    {
        $group->update([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? $group->sort_order),
        ]);

        return $group->refresh();
    }

    /** A slug of the name, numbered when already taken ("khach-hang", "khach-hang-2"…). */
    public function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'nhom';
        $base = Str::limit($base, 90, '');
        $key = $base;
        $suffix = 2;

        while (PartnerGroup::query()->where('key', $key)->exists()) {
            $key = "{$base}-{$suffix}";
            $suffix++;
        }

        return $key;
    }

    /** @param  array<string, mixed>  $data */
    private function logo(array $data): ?string
    {
        $file = $data['image_file'] ?? null;
        if ($file instanceof UploadedFile) {
            return $this->cloudinary->uploadFile($file, self::MEDIA_FOLDER);
        }

        return $this->nullableString($data['image_url'] ?? null);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
