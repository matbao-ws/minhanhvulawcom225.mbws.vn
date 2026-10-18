<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An AI button pressed on a create form saves the record as a draft first (admin.shared.ai-draft-launch
 * adds _ai_next and _ai_locale). The create then lands on the edit page, where that tool opens.
 */
trait OpensAiToolAfterCreate
{
    /** The tools a create form can hand over: the button class without "js-ai-". */
    private const AI_TOOLS = ['edit', 'faq', 'alt', 'links', 'product', 'collection', 'meta-suggest', 'seo-fix'];

    protected function redirectAfterCreate(Request $request, string $editRoute, Model $record, RedirectResponse $default): RedirectResponse
    {
        $tool = $request->input('_ai_next');
        if (! is_string($tool) || ! in_array($tool, self::AI_TOOLS, true)) {
            return $default;
        }

        $locale = $request->input('_ai_locale');
        $query = ['ai' => $tool] + (is_string($locale) && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $locale) ? ['ai_locale' => $locale] : []);

        return redirect()->route($editRoute, [$record] + $query)->with('success', __('ai.draft_first.saved'));
    }
}
