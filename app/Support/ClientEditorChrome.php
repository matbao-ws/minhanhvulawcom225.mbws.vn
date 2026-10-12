<?php

namespace App\Support;

use App\Models\User;

/**
 * Whether this request should be served the storefront editor's own furniture.
 *
 * Exists for one caller: the responsive preview opens the current page in an
 * iframe, and without this the preview would contain a second admin bar, a second
 * outline panel and a second owner of edit mode inside it.
 *
 * The flag can only ever **remove** the editor. It grants nothing, so it is not an
 * authorization decision and must never be mistaken for one — that stays with
 * {@see User::canEditClientContent()}, which every partial still
 * checks. Named here rather than typed into the layout so the preview and the
 * layout cannot drift on the spelling.
 */
class ClientEditorChrome
{
    public const FLAG = 'client_chrome';

    public const OFF = 'off';

    public function __construct() {}

    /**
     * Whether this request should receive any editor affordance at all.
     *
     * The single gate every storefront component and editor partial asks. Two
     * separate conditions, and the order matters for reading it correctly: the
     * permission decides *whether* the editor may appear, the flag only decides
     * whether this particular render wants it. Removing the flag can never grant
     * anything, because the permission is still the left-hand side.
     *
     * Routed through one method rather than repeated at a dozen call sites so the
     * preview frame cannot end up honest in the partials and lying in the
     * components — a class emitted only for an editor would make the preview differ
     * from what a visitor sees, which is the one thing a preview must not do.
     */
    public static function editorActive(): bool
    {
        return (bool) auth()->user()?->canEditClientContent() && ! self::suppressed();
    }

    /**
     * True when the caller asked for the page without editor furniture.
     */
    public static function suppressed(): bool
    {
        return request()->query(self::FLAG) === self::OFF;
    }

    /**
     * The current URL with the editor suppressed, for the preview frame.
     */
    public static function previewUrl(): string
    {
        return request()->fullUrlWithQuery([self::FLAG => self::OFF]);
    }
}
