<?php

namespace App\Support\CoreUpdate;

use App\Models\ProjectSetting;
use App\Models\User;

/**
 * Who may open the update page and install an offered release.
 *
 * Like WordPress: the website's own admin (`settings.update`) updates by default, and the
 * superadmin (the developer) can take that away per website. Rollback, baseline and this
 * switch stay superadmin-only — they are repairs, not routine updates.
 */
class CoreUpdateAccess
{
    public const SETTING_KEY = 'core_update_admin_access';

    /** Off only once a superadmin turned it off: a website with no row lets its admin update. */
    public function adminAllowed(): bool
    {
        $value = ProjectSetting::query()->where('setting_key', self::SETTING_KEY)->first()?->setting_value;

        return $value === null || (bool) $value;
    }

    public function setAdminAllowed(bool $allowed): void
    {
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => self::SETTING_KEY],
            ['setting_value' => $allowed, 'updated_at' => now()],
        );
    }

    public function allows(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin()
            || ($user->can('settings.update') && $this->adminAllowed());
    }
}
