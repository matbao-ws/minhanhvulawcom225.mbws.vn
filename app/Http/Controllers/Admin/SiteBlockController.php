<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteBlockUpdateRequest;
use App\Http\Requests\Admin\SiteListItemRequest;
use App\Http\Requests\Admin\SiteListReorderRequest;
use App\Models\SiteBlock;
use App\Services\ActivityLogger;
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

        // Replacing an image costs the media permission on top of pages.update.
        if ($data['type'] === SiteBlock::TYPE_IMAGE) {
            abort_unless($request->user()?->can('media.view'), 403, 'Unauthorized.');
        }

        $block = $this->site->updateLocale(
            $data['key'],
            $data['type'],
            $data['content_locale'],
            $data['value'],
            $request->user()?->id,
            $data['format'] ?? null,
        );

        ActivityLogger::log('updated', $block, "Sửa vùng nội dung {$data['key']}", [
            'new' => ['key' => $data['key'], 'locale' => $data['content_locale']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu.',
            'data' => [
                'key' => $data['key'],
                // The server's value, so the admin sees what sanitizing did.
                'value' => $this->site->value($data['key'], $data['content_locale']),
                'cleared' => $this->site->isCleared($data['key'], $data['content_locale']),
                // Echoed back so the editor can drop a heading class it applied
                // optimistically if the server refused the format.
                'format' => $this->site->format($data['key']),
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

        ActivityLogger::log('created', null, "Thêm ô vào vùng {$data['key']}", [
            'model' => SiteBlock::class,
            'new' => ['key' => $data['key'], 'item' => $itemId],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm ô nội dung.',
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

        ActivityLogger::log('updated', null, "Sắp xếp lại vùng {$data['key']}", [
            'model' => SiteBlock::class,
            'new' => ['key' => $data['key'], 'order' => $data['order']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã đổi thứ tự.',
            'data' => ['key' => $data['key'], 'order' => $this->lists->items($data['key'])],
        ]);
    }

    /**
     * Remove one added box and the content it owned.
     */
    public function removeListItem(SiteListItemRequest $request, string $locale): JsonResponse
    {
        $data = $request->validated();

        abort_if(blank($data['item'] ?? null), 422, 'Thiếu mã ô cần xóa.');

        $this->lists->remove($data['key'], $data['item'], $data['defaults'] ?? []);

        ActivityLogger::log('deleted', null, "Xóa ô khỏi vùng {$data['key']}", [
            'model' => SiteBlock::class,
            'old' => ['key' => $data['key'], 'item' => $data['item']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa ô nội dung.',
            'data' => ['key' => $data['key'], 'item' => $data['item']],
        ]);
    }

    /**
     * Drop the override so the template's own content returns.
     */
    public function restore(Request $request, string $locale): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:191'],
            'content_locale' => ['required', 'string', Rule::in(app(\App\Services\LanguageRegistry::class)->codes())],
        ]);

        $this->site->restoreLocale($data['key'], $data['content_locale'], $request->user()?->id);

        ActivityLogger::log('deleted', null, "Khôi phục vùng nội dung {$data['key']}", [
            'model' => SiteBlock::class,
            'old' => ['key' => $data['key'], 'locale' => $data['content_locale']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã khôi phục nội dung gốc.',
            'data' => ['key' => $data['key']],
        ]);
    }
}
