<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesLocalizedInput;
use App\Models\MenuItem;
use App\Services\MenuService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MenuItemRequest extends FormRequest
{
    use NormalizesLocalizedInput;

    public function authorize(): bool
    {
        // Route middleware owns authorization for admin screens.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeLocalizedInput(['label']);
    }

    public function rules(): array
    {
        $menu = $this->route('menu');

        return [
            ...$this->localizedStringRules('label', true, 255),
            'type' => ['required', Rule::in(MenuItem::types())],
            'parent_id' => [
                'nullable',
                'integer',
                // Scoped to this menu: an item may never adopt a parent from
                // another menu, which would silently move it.
                Rule::exists('menu_items', 'id')->where('menu_id', $menu?->id),
            ],
            'page_id' => ['nullable', 'integer', 'exists:pages,id', 'required_if:type,'.MenuItem::TYPE_PAGE],
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_if:type,'.MenuItem::TYPE_CATEGORY],
            'post_category_id' => ['nullable', 'integer', 'exists:post_categories,id', 'required_if:type,'.MenuItem::TYPE_POST_CATEGORY],
            'url' => ['nullable', 'string', 'max:2048', 'required_if:type,'.MenuItem::TYPE_URL],
            'target_blank' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectUnsafeUrl($validator);
            $this->rejectCyclicParent($validator);
        });
    }

    /**
     * The custom URL lands directly in an href, so anything that is not a
     * same-site path or an http(s) link is refused.
     */
    private function rejectUnsafeUrl(Validator $validator): void
    {
        if ($this->input('type') !== MenuItem::TYPE_URL) {
            return;
        }

        $url = trim((string) $this->input('url'));

        if ($url === '') {
            return;
        }

        $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! $isRelative && ! in_array($scheme, ['http', 'https'], true)) {
            $validator->errors()->add('url', __('admin.menus.validation.url_scheme'));
        }
    }

    /**
     * An item cannot sit under itself or under one of its own descendants;
     * either would detach the branch from the tree and loop the renderer.
     */
    private function rejectCyclicParent(Validator $validator): void
    {
        $parentId = $this->input('parent_id');
        $item = $this->route('item');

        if (! $parentId || ! $item instanceof MenuItem) {
            return;
        }

        if (in_array((int) $parentId, app(MenuService::class)->descendantIds($item), true)) {
            $validator->errors()->add('parent_id', __('admin.menus.validation.parent_cycle'));
        }
    }
}
