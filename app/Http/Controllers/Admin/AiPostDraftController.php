<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectSetting;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\PostDraftService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Write a post with AI": a form with the owner's facts (or notes), then an
 * unpublished post opened in the normal editor. Route: feature:ai_seo +
 * feature:cms_page + can:ai.use + can:posts.create + package.limit:data_unit.
 */
class AiPostDraftController extends Controller
{
    /** The business description is reused for the next draft so the owner types it once. */
    public const BUSINESS_SETTING = 'ai_business_info';

    public function __construct(private readonly PostDraftService $drafts) {}

    public function create(): View
    {
        $saved = ProjectSetting::query()->where('setting_key', self::BUSINESS_SETTING)->value('setting_value');

        return view('admin.ai.post-drafts.create', [
            'businessInfo' => is_string($saved) ? $saved : '',
            'tones' => PostDraftService::TONES,
            'minWords' => PostDraftService::MIN_WORDS,
            'maxWords' => PostDraftService::MAX_WORDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mode = $request->input('mode') === 'notes' ? 'notes' : 'draft';
        $words = ['required', 'integer', 'min:'.PostDraftService::MIN_WORDS, 'max:'.PostDraftService::MAX_WORDS];
        // A search phrase, not a sentence: the SEO checker needs it verbatim in a 60-character title.
        $keyword = ['string', 'min:2', 'max:120', function (string $attribute, mixed $value, \Closure $fail) {
            if (count(preg_split('/\s+/u', trim((string) $value))) > PostDraftService::MAX_KEYWORD_WORDS) {
                $fail(__('ai.draft.keyword_too_long', ['max' => PostDraftService::MAX_KEYWORD_WORDS]));
            }
        }];

        $data = $mode === 'draft'
            ? $request->validate([
                'keyword' => ['required', ...$keyword],
                'business_info' => ['required', 'string', 'min:30', 'max:3000'],
                'key_points' => ['required', 'string', 'min:20', 'max:4000'],
                'products' => ['nullable', 'string', 'max:3000'],
                'audience' => ['required', 'string', 'min:3', 'max:200'],
                'tone' => ['required', Rule::in(PostDraftService::TONES)],
                'sources' => ['nullable', 'string', 'max:3000'],
                'target_words' => $words,
            ])
            : $request->validate([
                'title' => ['nullable', 'string', 'max:200'],
                'keyword' => ['nullable', ...$keyword],
                'notes' => ['required', 'string', 'min:50', 'max:15000'],
                'tone' => ['nullable', Rule::in(PostDraftService::TONES)],
                'target_words' => $words,
            ]);

        if ($mode === 'draft') {
            // One product per line; the pasted sources travel as one excerpt.
            $data['products'] = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['products'] ?? ''))))), 0, 10);
            $data['sources'] = filled($data['sources'] ?? null) ? [trim($data['sources'])] : [];
        }
        $data['target_words'] = (int) $data['target_words'];

        try {
            $post = $this->drafts->create($mode, $data, $request->user());
        } catch (GatewayUnavailable $e) {
            $key = 'ai.errors.'.$e->reason;

            return back()->withInput()->with('error', __($key) !== $key ? __($key) : __('ai.errors.default'));
        }

        if ($mode === 'draft') {
            ProjectSetting::query()->updateOrCreate(['setting_key' => self::BUSINESS_SETTING], ['setting_value' => $data['business_info']]);
        }

        return redirect()->route('admin.posts.edit', $post)->with('success', __('ai.draft.created'));
    }
}
