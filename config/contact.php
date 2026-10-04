<?php

/*
 * Public contact form (POST /api/public/contact) spam filter. See App\Services\Contact\ContactSpamGuard.
 */
return [
    // Per IP. The per-minute limit answers 429; the daily one too.
    'per_minute' => (int) env('CONTACT_PER_MINUTE', 5),
    'per_day' => (int) env('CONTACT_PER_DAY', 20),

    // A submission whose message holds this many links or more is spam.
    'max_links' => (int) env('CONTACT_MAX_LINKS', 3),

    // With the optional `_ts` field (when the form was shown), faster than this is a bot.
    'min_fill_seconds' => (int) env('CONTACT_MIN_FILL_SECONDS', 3),

    // Words that mark a submission as spam (case-insensitive, whole message, name and email).
    // A website adds its own with CONTACT_BLOCKED_WORDS="word one,word two".
    'blocked_words' => array_values(array_filter(array_merge(
        ['viagra', 'cialis', 'casino', 'backlink', 'porn', 'nhà cái', 'tài xỉu', 'cá cược', 'lô đề', 'xóc đĩa'],
        array_map('trim', explode(',', (string) env('CONTACT_BLOCKED_WORDS', ''))),
    ))),
];
