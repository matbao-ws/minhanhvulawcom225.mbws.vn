<?php

namespace App\Services\Contact;

use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Decides whether a public contact submission is spam, without asking anything of the website's
 * form: existing themes keep working unchanged. Two optional fields make it stronger when a
 * theme adds them:
 *
 *   _hp  a hidden input people never see, so never fill (bots fill every field)
 *   _ts  unix time the form was shown; a submission a few seconds later is a script
 *
 * The caller answers spam exactly like a real submission, so a bot learns nothing.
 */
class ContactSpamGuard
{
    /** Fields the guard reads; never stored as the inquiry's extra info. */
    public const CONTROL_FIELDS = ['_hp', '_ts'];

    public const DUPLICATE = 'duplicate';

    /** @return string|null why it is spam: honeypot, too_fast, link_in_name, html_link, too_many_links, blocked_word, duplicate */
    public function reason(Request $request, array $inquiry): ?string
    {
        if (filled($request->input('_hp'))) {
            return 'honeypot';
        }

        $shownAt = $request->input('_ts');
        if (is_numeric($shownAt)) {
            $shownAt = (int) $shownAt > 9_999_999_999 ? intdiv((int) $shownAt, 1000) : (int) $shownAt; // ms or s
            if (now()->timestamp - $shownAt < (int) config('contact.min_fill_seconds')) {
                return 'too_fast';
            }
        }

        $name = (string) ($inquiry['name'] ?? '');
        $message = (string) ($inquiry['message'] ?? '');

        if (preg_match('~https?://|www\.|<a\s~i', $name)) {
            return 'link_in_name';
        }
        if (preg_match('~<a\s[^>]*href|\[url[=\]]~i', $message)) {
            return 'html_link';
        }
        if (preg_match_all('~https?://|www\.~i', $message) >= (int) config('contact.max_links')) {
            return 'too_many_links';
        }

        $text = Str::lower($name.' '.($inquiry['email'] ?? '').' '.$message);
        foreach ((array) config('contact.blocked_words') as $word) {
            if ($word !== '' && str_contains($text, Str::lower($word))) {
                return 'blocked_word';
            }
        }

        // The same person sending the same text again within a day: keep the first one only.
        $duplicate = ContactSubmission::query()
            ->where('phone', $inquiry['phone'] ?? '')
            ->where('message', $message)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        return $duplicate ? self::DUPLICATE : null;
    }
}
