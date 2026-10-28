<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'changes',
        'ip_address',
    ];

    protected $casts = ['changes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Keep superadmin activity private from ordinary administrators.
     *
     * The restriction is expressed on the log query so it covers the rows,
     * filter options, and any future use of this listing query consistently.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        return $query->whereDoesntHave('user.role', function (Builder $roleQuery): void {
            $roleQuery->where('is_superadmin', true);
        });
    }

    public static function actionLabelFor(?string $action): string
    {
        $key = 'admin.activity_logs.labels.actions.'.$action;

        return $action !== null && trans()->has($key) ? __($key) : Str::headline((string) $action);
    }

    public static function subjectLabelFor(?string $model, bool $plural = false): string
    {
        $labels = [
            Product::class => 'product',
            Category::class => 'category',
            Brand::class => 'brand',
            Post::class => 'post',
            Banner::class => 'banner',
            Voucher::class => 'voucher',
            User::class => 'user',
            Role::class => 'role',
            Order::class => 'order',
        ];

        if (isset($labels[$model])) {
            return __('admin.activity_logs.labels.subjects.'.$labels[$model].'.'.($plural ? 'many' : 'one'));
        }

        return $model ? Str::headline(class_basename($model)) : '—';
    }

    public function displayActionLabel(): string
    {
        return self::actionLabelFor($this->action);
    }

    public function displaySubjectLabel(): string
    {
        return self::subjectLabelFor($this->changedModel());
    }

    public function displayDescription(): string
    {
        if (! in_array($this->action, ['bulk_deleted', 'bulk_status_changed'], true)) {
            return $this->description;
        }

        $changes = $this->changeData();
        $count = (int) ($changes['count'] ?? 0);
        $subject = self::subjectLabelFor($this->changedModel(), true);

        if ($this->action === 'bulk_deleted') {
            return __('admin.activity_logs.labels.bulk_deleted', ['count' => $count, 'subject' => $subject]);
        }

        return ($changes['is_active'] ?? false)
            ? __('admin.activity_logs.labels.bulk_activated', ['count' => $count, 'subject' => $subject])
            : __('admin.activity_logs.labels.bulk_deactivated', ['count' => $count, 'subject' => $subject]);
    }

    /** @return array<string, string> */
    public function displayChanges(): array
    {
        $changes = $this->changeData();
        if ($changes === []) {
            return [];
        }

        if (in_array($this->action, ['bulk_deleted', 'bulk_status_changed'], true)) {
            $count = (int) ($changes['count'] ?? 0);
            $label = __($this->action === 'bulk_deleted' ? 'admin.activity_logs.labels.deleted_count' : 'admin.activity_logs.labels.updated_count');
            $summary = [$label => "{$count} ".self::subjectLabelFor($this->changedModel(), true)];

            if ($this->action === 'bulk_status_changed') {
                $summary[__('admin.activity_logs.labels.new_status')] = __(($changes['is_active'] ?? false) ? 'admin.activity_logs.labels.active' : 'admin.activity_logs.labels.inactive');
            }

            return $summary;
        }

        $summary = [];
        foreach ($changes as $key => $value) {
            if (in_array($key, ['model', 'ids', 'count'], true)) {
                continue;
            }

            $summary[self::fieldLabel((string) $key)] = self::formatChangeValue($value);
        }

        return $summary;
    }

    private function changedModel(): ?string
    {
        $model = $this->changeData()['model'] ?? $this->subject_type;

        return is_string($model) ? $model : null;
    }

    /** @return array<string, mixed> */
    private function changeData(): array
    {
        $changes = $this->getAttribute('changes');

        return is_array($changes) ? $changes : [];
    }

    private static function fieldLabel(string $field): string
    {
        $key = 'admin.activity_logs.labels.fields.'.$field;

        return trans()->has($key) ? __($key) : Str::headline($field);
    }

    private static function formatChangeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return __($value ? 'admin.activity_logs.labels.active' : 'admin.activity_logs.labels.inactive');
        }
        if ($value === null || $value === '') {
            return __('admin.activity_logs.labels.none');
        }
        if ($value === '[REDACTED]') {
            return __('admin.activity_logs.labels.redacted');
        }
        if (! is_array($value)) {
            return (string) $value;
        }

        if (array_is_list($value)) {
            return implode(', ', array_map(fn ($item) => self::formatChangeValue($item), $value));
        }

        return collect($value)
            ->map(fn ($item, $key) => self::fieldLabel((string) $key).': '.self::formatChangeValue($item))
            ->implode('; ');
    }
}
