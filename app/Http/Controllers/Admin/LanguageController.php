<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\LanguageRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function index()
    {
        return view('admin.languages.index', [
            'languages' => Language::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, LanguageRegistry $registry)
    {
        $validated = $request->validate($this->rules());
        Language::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'is_default' => false,
            'is_content_fallback' => false,
        ]);
        $registry->forget();

        return back()->with('success', 'Đã thêm ngôn ngữ. Bạn có thể bắt đầu nhập nội dung cho locale mới.');
    }

    public function update(Request $request, string $locale, Language $language, LanguageRegistry $registry)
    {
        $validated = $request->validate($this->rules($language));
        $isActive = $request->boolean('is_active');

        if (($language->is_default || $language->is_content_fallback) && ! $isActive) {
            return back()->withErrors(['is_active' => 'Ngôn ngữ mặc định hoặc fallback phải được kích hoạt.']);
        }

        $language->update([
            ...$validated,
            'is_active' => $isActive,
        ]);
        $registry->forget();

        $redirectLocale = $registry->supportsAdmin(app()->getLocale())
            ? app()->getLocale()
            : $registry->defaultLocale();

        return redirect()->route('admin.languages.index', ['locale' => $redirectLocale])
            ->with('success', 'Đã cập nhật ngôn ngữ.');
    }

    public function updatePreferences(Request $request, string $locale, LanguageRegistry $registry)
    {
        $activeLanguage = Rule::exists('languages', 'code')->where(
            fn ($query) => $query->where('is_active', true),
        );

        $validated = $request->validate([
            'default_locale' => ['required', 'string', $activeLanguage],
            'fallback_locale' => ['required', 'string', 'different:default_locale', $activeLanguage],
        ], [
            'fallback_locale.different' => 'Ngôn ngữ fallback phải khác ngôn ngữ mặc định.',
        ]);

        DB::transaction(function () use ($validated): void {
            Language::query()->update([
                'is_default' => false,
                'is_content_fallback' => false,
            ]);

            Language::query()->where('code', $validated['default_locale'])->update(['is_default' => true]);
            Language::query()->where('code', $validated['fallback_locale'])->update(['is_content_fallback' => true]);
        });
        $registry->forget();

        return back()->with('success', 'Đã cập nhật ngôn ngữ mặc định và fallback nội dung.');
    }

    private function rules(?Language $language = null): array
    {
        return [
            'code' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}$/', Rule::unique('languages', 'code')->ignore($language?->id)],
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['required', 'string', 'max:100'],
            'regional' => ['nullable', 'string', 'max:24'],
            'flag_path' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
