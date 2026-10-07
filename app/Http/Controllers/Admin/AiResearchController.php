<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\KeywordResearchService;
use App\Support\FeatureGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Keywords & competitors": competitors, keyword gaps, rank tracking and topic ideas.
 * Route: feature:ai_seo + feature:ai_research + can:ai.use. Every action redirects back to the page.
 */
class AiResearchController extends Controller
{
    public function __construct(private readonly KeywordResearchService $research) {}

    public function index(Request $request, FeatureGate $features): View
    {
        $research = null;
        $error = null;

        try {
            $research = $this->research->overview();
        } catch (GatewayUnavailable $e) {
            $error = self::message($e);
        }

        // "Write post" opens the AI draft screen, which has its own feature and permission.
        $canWrite = $request->user()->can('posts.create') && $features->availableTo($request->user(), 'cms_page');

        return view('admin.ai.research.index', compact('research', 'error', 'canWrite'));
    }

    /**
     * Polled by the page while the gateway is still fetching: what is left, on the gateway's clock.
     * The page reloads itself once nothing is pending.
     */
    public function status(): JsonResponse
    {
        try {
            $research = $this->research->overview(fresh: true);
        } catch (GatewayUnavailable $e) {
            return response()->json(['success' => false, 'message' => self::message($e)]);
        }

        return response()->json(['success' => true, 'data' => [
            'now' => $research['now'],
            'pending' => KeywordResearchService::pending($research),
        ]]);
    }

    public function storeCompetitor(Request $request): RedirectResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:255']]);

        return $this->attempt(fn () => $this->research->addCompetitor($data['domain']), __('ai.research.competitor_added'));
    }

    public function destroyCompetitor(Request $request): RedirectResponse
    {
        // By name: the {locale} prefix would fill a positional string parameter.
        $domain = (string) $request->route('domain');

        return $this->attempt(fn () => $this->research->removeCompetitor($domain), __('ai.research.competitor_removed'));
    }

    public function suggest(): RedirectResponse
    {
        try {
            $suggestions = $this->research->suggestCompetitors();
        } catch (GatewayUnavailable $e) {
            return back()->with('error', self::message($e));
        }

        return back()->with('researchSuggestions', $suggestions);
    }

    public function refresh(): RedirectResponse
    {
        try {
            $queued = $this->research->refreshGaps();
        } catch (GatewayUnavailable $e) {
            return back()->with('error', self::message($e));
        }

        return back()->with('success', $queued > 0 ? __('ai.research.refreshing', ['count' => $queued]) : __('ai.research.fresh'));
    }

    public function storeKeywords(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // One per line from the textarea, or a single keyword from a gap row.
            'keywords' => ['required', 'string', 'max:5000'],
        ]);
        $keywords = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['keywords']) ?: [])));
        if ($keywords === [] || count($keywords) > 50) {
            return back()->withInput()->with('error', __('ai.research.keywords_invalid'));
        }

        try {
            $result = $this->research->track(array_map(fn (string $keyword) => mb_substr($keyword, 0, 300), $keywords));
        } catch (GatewayUnavailable $e) {
            return back()->withInput()->with('error', self::message($e));
        }

        return back()->with('success', __('ai.research.keywords_added', ['count' => $result['added']]));
    }

    public function destroyKeyword(Request $request): RedirectResponse
    {
        $id = (int) $request->route('keyword');

        return $this->attempt(fn () => $this->research->untrack($id), __('ai.research.keyword_removed'));
    }

    public function topics(string $locale): RedirectResponse
    {
        try {
            $result = $this->research->topics($locale);
        } catch (GatewayUnavailable $e) {
            return back()->with('error', $e->reason === 'invalid_input' ? __('ai.research.topics_need_keywords') : self::message($e));
        }

        return back()->with('researchTopics', $result['topics']);
    }

    /** A sentence for the owner; the reason code alone never reaches the page. */
    public static function message(GatewayUnavailable $e): string
    {
        return self::reasonMessage($e->reason);
    }

    public static function reasonMessage(string $reason): string
    {
        foreach (['ai.research.errors.'.$reason, 'ai.errors.'.$reason] as $key) {
            if (__($key) !== $key) {
                return __($key);
            }
        }

        return __('ai.errors.default');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (GatewayUnavailable $e) {
            return back()->withInput()->with('error', self::message($e));
        }

        return back()->with('success', $success);
    }
}
