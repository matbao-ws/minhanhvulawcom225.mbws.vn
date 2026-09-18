<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteSectionUpdateRequest;
use App\Models\SiteSectionRevision;
use App\Services\ActivityLogger;
use App\Services\SiteSectionService;
use App\Support\SectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The single write path for how a storefront section renders.
 *
 * Session + CSRF + permission + throttle on an admin route, sharing the gate the
 * region edits use: choosing a section's variant is the same power over the same
 * site as rewriting its heading. Never exposed under a public API prefix.
 *
 * What is being written is a choice among shapes the template already knows how
 * to render — never CSS, never a position, never a new element.
 */
class SiteSectionController extends Controller
{
    public function __construct(
        private readonly SiteSectionService $sections,
        private readonly SectionRegistry $registry,
    ) {}

    public function update(SiteSectionUpdateRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();
        $listKey = $data['list_key'];
        $section = $data['section'];

        $stored = $this->sections->update(
            $listKey,
            $section,
            $data['variant'] ?? null,
            $data['settings'] ?? [],
            (bool) $data['is_visible'],
            $request->user()?->id,
        );

        // No row means every override is gone, which is a restore however it was
        // reached. Logging it as an update would leave the trail claiming a change
        // that no longer exists.
        ActivityLogger::log(
            $stored === null ? 'restored' : 'updated',
            $stored,
            ($stored === null ? 'Trả section về mặc định ' : 'Sửa hiển thị section ').$listKey.'.'.$section,
            ['new' => ['list_key' => $listKey, 'section' => $section]],
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu.',
            'data' => $this->state($listKey, $section),
        ]);
    }

    /**
     * Drop every override so the section renders the way the design does.
     *
     * Separate from sending an empty payload to `update`: this one is explicit
     * about intent, and it is the operation the editor's "về mặc định" control
     * calls, so the activity log can say which of the two happened.
     */
    public function restore(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'list_key' => ['required', 'string', 'max:150'],
            'section' => ['required', 'string', 'max:40'],
        ]);

        $this->sections->restore($data['list_key'], $data['section'], $request->user()?->id);

        ActivityLogger::log(
            'restored',
            null,
            "Trả section về mặc định {$data['list_key']}.{$data['section']}",
            ['new' => ['list_key' => $data['list_key'], 'section' => $data['section']]],
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã trả về mặc định.',
            'data' => $this->state($data['list_key'], $data['section']),
        ]);
    }

    /** The saved history of one section, behind the same gate as the write. */
    public function revisions(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'list_key' => ['required', 'string', 'max:150'],
            'section' => ['required', 'string', 'max:40'],
        ]);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => ['revisions' => $this->sections->revisions($data['list_key'], $data['section'])],
        ]);
    }

    /**
     * Put one snapshot back.
     *
     * The section is taken from the revision, not from the request: accepting both
     * would let a crafted call write one section's appearance onto another.
     */
    public function restoreRevision(Request $request, string $locale, SiteSectionRevision $revision): JsonResponse
    {
        $stored = $this->sections->restoreRevision($revision, $request->user()?->id);

        [$listKey, $section] = $this->split((string) $stored?->key);

        ActivityLogger::log('restored', $stored, "Khôi phục phiên bản hiển thị section {$stored?->key}", [
            'new' => ['key' => $stored?->key, 'revision_id' => $revision->id],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã khôi phục phiên bản.',
            'data' => $this->state($listKey, $section),
        ]);
    }

    /**
     * Split a storage key back into its list and section.
     *
     * Safe because a section name may never contain a dot — the rule that exists so
     * a name cannot address a different section's row.
     *
     * @return array{0: string, 1: string}
     */
    private function split(string $key): array
    {
        $parts = explode('.', $key);
        $section = (string) array_pop($parts);

        return [implode('.', $parts), $section];
    }

    /**
     * What the section looks like now, as the server sees it.
     *
     * Returned from both actions so the editor applies the classes the server
     * resolved rather than the ones it guessed — the same reason a region edit
     * echoes back the sanitized value.
     *
     * @return array<string, mixed>
     */
    private function state(string $listKey, string $section): array
    {
        $presentation = $this->sections->presentation($listKey, $section);

        return [
            'list_key' => $listKey,
            'section' => $section,
            'variant' => $this->sections->variant($listKey, $section),
            'variant_class' => $this->registry->variantClass($section, $this->sections->variant($listKey, $section)),
            'settings' => $this->sections->settings($listKey, $section),
            'hidden' => $this->sections->isHidden($listKey, $section),
            'classes' => $presentation['classes'],
            'styles' => $presentation['styles'],
        ];
    }
}
