<?php

namespace App\Support;

/**
 * What each storefront section lets an editor change about its own appearance.
 *
 * Code — not the database — owns this list, the same way {@see PermissionRegistry}
 * owns permission codes. Two properties follow from that and both matter:
 *
 * - **The database never holds a CSS class.** It holds a token (`primary`,
 *   `center`, `lg`); the class that token maps to is declared here. Repainting
 *   `client-section--bg-primary` later is a one-line CSS change rather than an
 *   UPDATE across every row that ever chose it.
 * - **A section not declared here simply offers no settings.** That is a legal
 *   state, not an oversight: it is the same contract as `site_blocks.format`,
 *   where null means the tag the Blade template authored still wins.
 *
 * There is deliberately no `default` anywhere. A setting has no value until an
 * editor picks one, and until then the wrapper carries no extra class at all —
 * which is what keeps a fresh install rendering the approved design.
 */
class SectionRegistry
{
    public const TYPE_TOKEN = 'token';

    public const TYPE_COLOR = 'color';

    /**
     * The settings a section may opt into, defined once and shared by name.
     *
     * Class names are written out in full rather than built with sprintf. A class
     * you cannot grep for is a class nobody can safely delete, and the CSS that
     * backs these lives in a hand-written stylesheet with no build step to catch
     * the mismatch.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SETTINGS = [
        'background' => [
            'label' => 'Màu nền',
            'type' => self::TYPE_TOKEN,
            'values' => [
                'light' => ['label' => 'Trắng', 'class' => 'client-section--bg-light'],
                'muted' => ['label' => 'Xám nhạt', 'class' => 'client-section--bg-muted'],
                'primary' => ['label' => 'Màu nhấn', 'class' => 'client-section--bg-primary'],
                'dark' => ['label' => 'Tối', 'class' => 'client-section--bg-dark'],
            ],
        ],
        'alignment' => [
            'label' => 'Căn chữ',
            'type' => self::TYPE_TOKEN,
            'values' => [
                'left' => ['label' => 'Trái', 'class' => 'client-section--align-left'],
                'center' => ['label' => 'Giữa', 'class' => 'client-section--align-center'],
                'right' => ['label' => 'Phải', 'class' => 'client-section--align-right'],
            ],
        ],
        'spacing' => [
            'label' => 'Khoảng cách trên dưới',
            'type' => self::TYPE_TOKEN,
            'values' => [
                'none' => ['label' => 'Không', 'class' => 'client-section--pad-none'],
                'sm' => ['label' => 'Hẹp', 'class' => 'client-section--pad-sm'],
                'md' => ['label' => 'Vừa', 'class' => 'client-section--pad-md'],
                'lg' => ['label' => 'Rộng', 'class' => 'client-section--pad-lg'],
            ],
        ],
        /*
         * A free colour cannot be a token — the whole point of a picker is that
         * the value was not known in advance. It rides as a CSS custom property on
         * the wrapper instead, so no class has to exist for a colour nobody chose
         * yet. See `.client-section--accent` in storefront.css: it redefines the
         * theme's own `--client-accent` inside the section, so links and the
         * primary background follow along without a second rule per component.
         */
        'accent' => [
            'label' => 'Màu nhấn riêng',
            'type' => self::TYPE_COLOR,
        ],
    ];

    /**
     * Sections, keyed by the Blade partial name under
     * `resources/views/client/sections/`.
     *
     * `variants` are the shapes the section itself knows how to render; the
     * partial receives the chosen one as `$sectionVariant` and decides what to do
     * with it. Splitting a variant into its own partial would make it a separate
     * section in the eyes of `:sections`, which is not the same thing at all.
     *
     * A variant is a *departure*. There is no variant meaning "the way the
     * template already does it" — null already means that, the same way an absent
     * setting does. Declaring one would force a stylesheet rule that has nothing
     * to say, and give the editor two ways to express one choice.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SECTIONS = [
        'intro' => [
            'label' => 'Giới thiệu',
            'variants' => ['boxed' => 'Trong khung'],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'features' => [
            'label' => 'Tính năng',
            'variants' => ['grid' => 'Dạng lưới'],
            'settings' => ['background', 'alignment', 'spacing'],
        ],
        'contact' => [
            'label' => 'Liên hệ',
            'variants' => [],
            'settings' => ['background', 'spacing'],
        ],
        'speed' => [
            'label' => 'Tốc độ',
            'variants' => [],
            'settings' => ['background', 'alignment'],
        ],
        'safety' => [
            'label' => 'An toàn',
            'variants' => [],
            'settings' => ['background', 'alignment'],
        ],
        'support' => [
            'label' => 'Hỗ trợ',
            'variants' => [],
            'settings' => ['background', 'alignment'],
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public function sections(): array
    {
        return self::SECTIONS;
    }

    /** @return array<string, array<string, mixed>> */
    public function settingCatalogue(): array
    {
        return self::SETTINGS;
    }

    /** True when this section offers anything at all to change. */
    public function has(string $section): bool
    {
        return array_key_exists($section, self::SECTIONS);
    }

    public function label(string $section): string
    {
        return self::SECTIONS[$section]['label'] ?? $section;
    }

    /** @return array<string, string> variant token => label */
    public function variants(string $section): array
    {
        return self::SECTIONS[$section]['variants'] ?? [];
    }

    /** @return array<int, string> the setting names this section opted into */
    public function settingNames(string $section): array
    {
        return self::SECTIONS[$section]['settings'] ?? [];
    }

    public function allowsVariant(string $section, ?string $variant): bool
    {
        return $variant === null || array_key_exists($variant, $this->variants($section));
    }

    public function allowsSetting(string $section, string $setting): bool
    {
        return in_array($setting, $this->settingNames($section), true);
    }

    /** @return array<string, mixed>|null */
    public function settingSpec(string $section, string $setting): ?array
    {
        return $this->allowsSetting($section, $setting) ? (self::SETTINGS[$setting] ?? null) : null;
    }

    /**
     * True when `$value` is something an editor may store for this setting.
     *
     * Null always passes: it is how a setting is cleared back to whatever the
     * template does on its own.
     */
    public function allowsValue(string $section, string $setting, mixed $value): bool
    {
        $spec = $this->settingSpec($section, $setting);

        if ($spec === null) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return match ($spec['type']) {
            self::TYPE_TOKEN => array_key_exists($value, $spec['values'] ?? []),
            // Six hex digits only. Named colours, rgb() and var() would all reach
            // an inline style attribute, and only this shape cannot carry anything
            // except a colour.
            self::TYPE_COLOR => (bool) preg_match('/^#[0-9a-f]{6}$/i', $value),
            default => false,
        };
    }

    /**
     * The class one stored token maps to, or null when there is nothing to add.
     *
     * Null covers every "leave it alone" case — unknown section, a setting the
     * section does not offer, a value never chosen, or a colour, which is carried
     * as a custom property rather than a class.
     */
    public function tokenClass(string $section, string $setting, mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $spec = $this->settingSpec($section, $setting);

        if ($spec === null || ($spec['type'] ?? null) !== self::TYPE_TOKEN) {
            return null;
        }

        return $spec['values'][$value]['class'] ?? null;
    }

    /**
     * The class that marks a section as rendering one of its own variants.
     *
     * Scoped by section name: `boxed` means something different in a hero than in
     * a pricing table, and a shared `.client-section--boxed` would make one
     * section's styling leak into another's.
     */
    public function variantClass(string $section, ?string $variant): ?string
    {
        if ($variant === null || ! $this->allowsVariant($section, $variant)) {
            return null;
        }

        return 'client-section--'.$section.'-'.$variant;
    }

    /**
     * The class that switches on a colour setting.
     *
     * The colour itself arrives as an inline custom property, because no
     * stylesheet can hold a rule for a value the editor invented at pick time.
     * This class is what tells the CSS to start reading that property.
     */
    public function colorClass(string $setting): string
    {
        return 'client-section--'.$setting;
    }

    /**
     * The whole catalogue in the shape the editor panel consumes.
     *
     * Server-rendered rather than fetched: the panel needs labels and options to
     * draw anything at all, and a round trip for data that cannot change between
     * page loads would only add a spinner. Class names travel with it because the
     * panel previews a choice before Save, and previewing means putting the class
     * on the wrapper.
     *
     * Sections with nothing to offer are omitted, so the panel can never open on
     * an empty form.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schema(): array
    {
        $schema = [];

        foreach (self::SECTIONS as $section => $definition) {
            $variants = [];

            foreach ($definition['variants'] as $value => $label) {
                $variants[] = [
                    'value' => $value,
                    'label' => $label,
                    'class' => $this->variantClass($section, $value),
                ];
            }

            $settings = [];

            foreach ($definition['settings'] as $name) {
                $spec = self::SETTINGS[$name] ?? null;

                if ($spec === null) {
                    continue;
                }

                $values = [];

                foreach ($spec['values'] ?? [] as $value => $option) {
                    $values[] = [
                        'value' => $value,
                        'label' => $option['label'],
                        'class' => $option['class'],
                    ];
                }

                $settings[] = [
                    'name' => $name,
                    'label' => $spec['label'],
                    'type' => $spec['type'],
                    'values' => $values,
                    // Only meaningful for a colour: the class that tells the CSS to
                    // start reading the custom property this setting writes.
                    'class' => $spec['type'] === self::TYPE_COLOR ? $this->colorClass($name) : null,
                    'property' => $spec['type'] === self::TYPE_COLOR ? '--client-section-'.$name : null,
                ];
            }

            if ($variants === [] && $settings === []) {
                continue;
            }

            $schema[$section] = [
                'label' => $definition['label'],
                'variants' => $variants,
                'settings' => $settings,
            ];
        }

        return $schema;
    }

    /**
     * Every class this registry can put on a section wrapper.
     *
     * Exists so a test can assert the stylesheet actually defines them: there is
     * no build step on the client side to notice a token, a variant or a colour
     * whose class was never written.
     *
     * @return array<int, string>
     */
    public function allClasses(): array
    {
        $classes = [];

        foreach (self::SETTINGS as $name => $setting) {
            if ($setting['type'] === self::TYPE_COLOR) {
                $classes[] = $this->colorClass($name);
            }

            foreach ($setting['values'] ?? [] as $value) {
                $classes[] = $value['class'];
            }
        }

        foreach (self::SECTIONS as $section => $definition) {
            foreach (array_keys($definition['variants'] ?? []) as $variant) {
                $classes[] = $this->variantClass($section, $variant);
            }
        }

        return array_values(array_unique(array_filter($classes)));
    }
}
