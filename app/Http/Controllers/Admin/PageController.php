<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InlinePageUpdateRequest;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Models\PageRevision;
use App\Services\ActivityLogger;
use App\Services\LanguageRegistry;
use App\Services\PageContentService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        private readonly PageContentService $pages,
        private readonly LanguageRegistry $languages,
    ) {}

    public function index(Request $request)
    {
        $pages = Page::query()
            ->with('localizedSlugs')
            ->when($request->query('q'), function ($query, $keyword) {
                $query->where('slug', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%");
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.create', [
            'page' => new Page(['is_active' => false]),
            'contentLanguages' => $this->languages->active(),
            'defaultContentLocale' => $this->languages->defaultLocale(),
            'revisions' => collect(),
        ]);
    }

    public function store(PageRequest $request)
    {
        $page = $this->pages->create($request->validated());
        ActivityLogger::log('created', $page, "Tạo trang {$page->slug}");

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Đã tạo trang.');
    }

    public function edit(string $locale, Page $page)
    {
        return view('admin.pages.edit', [
            'page' => $page->load('localizedSlugs'),
            'contentLanguages' => $this->languages->active(),
            'defaultContentLocale' => $this->languages->defaultLocale(),
            'revisions' => $page->revisions()->with('creator')->limit(20)->get(),
        ]);
    }

    public function update(PageRequest $request, string $locale, Page $page)
    {
        $page = $this->pages->update($page, $request->validated(), $request->user()?->id);
        ActivityLogger::log('updated', $page, "Cập nhật trang {$page->slug}");

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Đã lưu trang.');
    }

    public function destroy(string $locale, Page $page)
    {
        $page->delete();
        ActivityLogger::log('deleted', $page, "Xóa trang {$page->slug}");

        return redirect()->route('admin.pages.index')->with('success', 'Đã xóa trang.');
    }

    public function preview(Request $request, string $locale, Page $page)
    {
        $previewLocale = $this->languages->resolve((string) $request->query('content_locale', $this->languages->defaultLocale()));

        return view('admin.pages.preview', [
            'page' => $page,
            'previewLocale' => $previewLocale,
            'html' => $page->getTranslation('published_html', $previewLocale, false),
        ]);
    }

    public function inlineUpdate(InlinePageUpdateRequest $request, string $locale, Page $page)
    {
        $validated = $request->validated();
        $contentLocale = $validated['content_locale'];
        $page = $this->pages->updateLocale($page, $contentLocale, $validated['published_html'], $request->user()?->id);
        ActivityLogger::log('updated', $page, "Sửa trực tiếp trang {$page->slug}", [
            'content_locale' => $contentLocale,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu trang.',
            'data' => [
                'html' => $page->getTranslation('published_html', $contentLocale, false),
                'updated_at' => $page->updated_at?->toISOString(),
            ],
        ]);
    }

    public function restore(Request $request, string $locale, Page $page, PageRevision $revision)
    {
        $page = $this->pages->restore($page, $revision, $request->user()?->id);
        ActivityLogger::log('restored', $page, "Khôi phục phiên bản trang {$page->slug}", ['revision_id' => $revision->id]);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Đã khôi phục phiên bản.');
    }
}
