<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\PermissionRegistry;

/**
 * Keeps permission management from becoming a privilege ladder.
 *
 * Roles used to be superadmin-only, so nothing stopped an editor from writing
 * themselves a more powerful role. Now that `roles.*` can be delegated, the
 * rule is: you may only hand out access you already hold.
 */
trait AuthorizesPermissionGrants
{
    /**
     * Permission codes the signed-in admin is allowed to hand out.
     *
     * @return array<int, string>
     */
    protected function grantableCodes(): array
    {
        $actor = auth()->user();

        return $actor?->isSuperAdmin()
            ? PermissionRegistry::codes()
            : ($actor?->effectivePermissionCodes() ?? []);
    }

    /**
     * @param  array<int, string>  $codes
     */
    protected function assertCanGrant(array $codes): void
    {
        $beyondReach = array_diff($codes, $this->grantableCodes());

        abort_unless($beyondReach === [], 403, 'Không thể cấp quyền vượt quá quyền của chính bạn.');
    }

    /**
     * Permission catalogue for the grant UI, grouped by module.
     *
     * Modules the actor cannot grant anything in are dropped so the form never
     * shows a switch that will be rejected on submit.
     *
     * @return array<string, array{label: string, group: string, actions: array<int, string>}>
     */
    protected function grantableModules(): array
    {
        $grantable = $this->grantableCodes();
        $modules = [];

        foreach (PermissionRegistry::modules() as $module => $definition) {
            $actions = array_values(array_filter(
                $definition['actions'],
                static fn (string $action): bool => in_array("{$module}.{$action}", $grantable, true),
            ));

            if ($actions !== []) {
                $modules[$module] = ['label' => $definition['label'], 'group' => $definition['group'], 'actions' => $actions];
            }
        }

        return $modules;
    }
}
