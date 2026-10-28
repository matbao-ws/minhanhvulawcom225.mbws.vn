<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteBlockUpdateRequest;
use App\Http\Requests\Admin\SiteListDuplicateRequest;
use App\Http\Requests\Admin\SiteListItemRequest;
use App\Http\Requests\Admin\SiteListReorderRequest;
use App\Models\SiteBlock;
use App\Models\SiteBlockRevision;
use App\Services\ActivityLogger;
use App\Services\LanguageRegistry;
use App\Services\SiteContentService;
use App\Services\SiteListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The single write path for inline storefront region edits.
 *
 * Session + CSRF + permission + throttle on an admin route; never exposed under
 * a public API prefix.
 */
class SiteBlockController extends Controller
{
    public function __construct(
        private readonly SiteContentService $site,
        private readonly SiteListService $lists,
    ) {}

    public function update(SiteBlockUpdateRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();

        // Replacing an image or a background costs the media permission on top of pages.update.
        if (in_array($data['type'], SiteBlock::PICTURE_TYPES, true)) {
            abort_unless($request->user()?->can('media.view'), 403, 'Unauthorized.');
        }

        $block = $this->site->updateLocale(
            $data['key'],
            $data['type'],
            $data['content_locale'],
            $data['value'],
            $request->user()?->id,
            $data['format'] ?? null,
            array_key_exists('align', $data) ? $data['align'] : false,
        );

        ActivityLogger::log('updated', $block, __('admin.site_editor.log.block_updated', ['key' => $data['key']]), [
            'new' => ['key' => $data['key'], 'locale' => $data['content_locale']],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.saved'),
            'data' => [
                'key' => $data['key'],
                // The server's value, so the admin sees what sanitizing did.
                'value' => $this->site->value($data['key'], $data['content_locale']),
                'cleared' => $this->site->isCleared($data['key'], $data['content_locale']),
                // Echoed back so the editor can drop a heading class it applied
                // optimistically if the server refused the format.
                'format' => $this->site->format($data['key']),
                'align' => $this->site->align($data['key']),
            ],
        ]);
    }

    /**
     * Add one box to a repeatable region.
     *
     * The region has to already be a list in the template: this appends an id to
     * an authored list, it does not create a place for content to go.
     */
    public function addListItem(SiteListItemRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();

        $itemId = $this->lists->add($data['key'], $data['defaults'] ?? []);

        ActivityLogger::log('created', null, __('admin.site_editor.log.item_added', ['key' => $data['key']]), [
            'model' => SiteBlock::class,
            'new' => ['key' => $data['key'], 'item' => $itemId],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.item_added'),
            'data' => ['key' => $data['key'], 'item' => $itemId],
        ]);
    }

    /**
     * Copy one box of a repeatable region, right after it, with what its regions show now.
     *
     * Still a box of a list the template built (rendered by that list's Blade), never new layout.
     * A picture slot needs the media permission, as editing it would; without it the copy keeps
     * the template's picture.
     */
    public function duplicateListItem(SiteListDuplicateRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $itemId = $this->lists->duplicate($data['key'], $data['item'] ?? null, $data['defaults'] ?? []);

        foreach ($data['slots'] as $slot) {
            if (in_array($slot['type'], SiteBlock::PICTURE_TYPES, true) && ! $user?->can('media.view')) {
                continue;
            }
            $html = $slot['type'] === SiteBlock::TYPE_HTML;
            $this->site->updateLocale(
                $this->lists->itemKey($data['key'], $itemId, $slot['slot']),
                $slot['type'],
                $data['content_locale'],
                $slot['value'],
                $user?->id,
                $html ? ($slot['format'] ?? null) : null,
                $slot['align'] ?? null,
            );
        }

        ActivityLogger::log('created', null, __('admin.site_editor.log.item_duplicated', ['key' => $data['key']]), [
            'model' => SiteBlock::class,
            'new' => ['key' => $data['key'], 'item' => $itemId, 'from' => $data['item'] ?? null],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.item_duplicated'),
            'data' => ['key' => $data['key'], 'item' => $itemId],
        ]);
    }

    /**
     * Change the order of the boxes in a repeatable region.
     *
     * Only boxes inside a list can move. A region authored in Blade sits where the
     * template puts it; storing that position in the database would be storing the
     * layout, which is the page builder this core deliberately does not have.
     */
    public function reorderListItems(SiteListReorderRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();

        $this->lists->reorder($data['key'], $data['order'], $data['defaults'] ?? []);

        ActivityLogger::log('updated', null, __('admin.site_editor.log.reordered', ['key' => $data['key']]), [
            'model' => SiteBlock::class,
            'new' => ['key' => $data['key'], 'order' => $data['order']],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.reordered'),
            'data' => ['key' => $data['key'], 'order' => $this->lists->items($data['key'])],
        ]);
    }

    /**
     * Remove one added box and the content it owned.
     */
    public function removeListItem(SiteListItemRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();

        abort_if(blank($data['item'] ?? null), 422, __('admin.site_editor.item_missing'));

        $this->lists->remove($data['key'], $data['item'], $data['defaults'] ?? []);

        ActivityLogger::log('deleted', null, __('admin.site_editor.log.item_removed', ['key' => $data['key']]), [
            'model' => SiteBlock::class,
            'old' => ['key' => $data['key'], 'item' => $data['item']],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.item_removed'),
            'data' => ['key' => $data['key'], 'item' => $data['item']],
        ]);
    }

    /**
     * Drop the override so the template's own content returns.
     */
    /**
     * The saved history of one region.
     *
     * Read behind the same gate as the write. History names the admins who edited
     * the site, which is not something a customer or a guest is owed.
     */
    public function revisions(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:191'],
        ]);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => ['revisions' => $this->site->revisions($data['key'], $locale)],
        ]);
    }

    /**
     * Put one snapshot back.
     *
     * The region is taken from the revision rather than from the request. Accepting
     * both would let a crafted call write revision A's content onto region B, and
     * there would be no way to tell from the audit trail.
     */
    public function restoreRevision(Request $request, string $locale, SiteBlockRevision $revision): JsonResponse
    {
        // Putting a picture back is changing a picture: same media permission as saving one.
        $this->authorizePicture($request, $revision->block?->type);
        $block = $this->site->restoreRevision($revision, $locale, $request->user()?->id);

        ActivityLogger::log('restored', $block, __('admin.site_editor.log.block_revision_restored', ['key' => (string) $block?->key]), [
            'new' => ['key' => $block?->key, 'locale' => $locale, 'revision_id' => $revision->id],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.revision_restored'),
            'data' => [
                'key' => $block?->key,
                'value' => $block ? $this->site->value($block->key, $locale) : null,
                'cleared' => $block ? $this->site->isCleared($block->key, $locale) : false,
                'format' => $block ? $this->site->format($block->key) : null,
            ],
        ]);
    }

    public function restore(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:191'],
            'content_locale' => ['required', 'string', Rule::in(app(LanguageRegistry::class)->codes())],
        ]);

        $this->authorizePicture($request, SiteBlock::query()->where('key', $data['key'])->value('type'));

        $this->site->restoreLocale($data['key'], $data['content_locale'], $request->user()?->id);

        ActivityLogger::log('deleted', null, __('admin.site_editor.log.block_restored', ['key' => $data['key']]), [
            'model' => SiteBlock::class,
            'old' => ['key' => $data['key'], 'locale' => $data['content_locale']],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('admin.site_editor.original_restored'),
            'data' => ['key' => $data['key']],
        ]);
    }

    private function authorizePicture(Request $request, ?string $type): void
    {
        if (in_array($type, SiteBlock::PICTURE_TYPES, true)) {
            abort_unless($request->user()?->can('media.view'), 403, 'Unauthorized.');
        }
    }
}
