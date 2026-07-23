<?php

namespace App\Models;

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

    public static function actionLabelFor(?string $action): string
    {
        return match ($action) {
            'created' => 'Tạo mới',
            'updated' => 'Cập nhật',
            'deleted' => 'Xóa',
            'status_changed' => 'Đổi trạng thái',
            'bulk_deleted' => 'Xóa nhiều',
            'bulk_status_changed' => 'Đổi trạng thái nhiều',
            'refunded' => 'Hoàn tiền',
            'shipping_pushed' => 'Gửi đơn vị vận chuyển',
            'impersonated' => 'Đăng nhập nhanh',
            default => Str::headline((string) $action),
        };
    }

    public static function subjectLabelFor(?string $model, bool $plural = false): string
    {
        $labels = [
            Product::class => ['Sản phẩm', 'sản phẩm'],
            Category::class => ['Danh mục', 'danh mục'],
            Brand::class => ['Thương hiệu', 'thương hiệu'],
            Post::class => ['Bài viết', 'bài viết'],
            Banner::class => ['Banner', 'banner'],
            Voucher::class => ['Mã giảm giá', 'mã giảm giá'],
            User::class => ['Tài khoản', 'tài khoản'],
            Role::class => ['Vai trò', 'vai trò'],
            Order::class => ['Đơn hàng', 'đơn hàng'],
        ];

        if (isset($labels[$model])) {
            return $labels[$model][$plural ? 1 : 0];
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
            return "Đã xóa {$count} {$subject} đã chọn.";
        }

        return ($changes['is_active'] ?? false)
            ? "Đã kích hoạt {$count} {$subject}."
            : "Đã tạm ẩn {$count} {$subject}.";
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
            $label = $this->action === 'bulk_deleted' ? 'Số lượng đã xóa' : 'Số lượng đã cập nhật';
            $summary = [$label => "{$count} ".self::subjectLabelFor($this->changedModel(), true)];

            if ($this->action === 'bulk_status_changed') {
                $summary['Trạng thái mới'] = ($changes['is_active'] ?? false) ? 'Đang hoạt động' : 'Tạm ẩn';
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
        return [
            'old' => 'Thông tin trước khi thay đổi',
            'new' => 'Thông tin sau khi thay đổi',
            'updated_keys' => 'Nội dung đã cập nhật',
            'is_active' => 'Trạng thái',
            'status' => 'Trạng thái đơn hàng',
            'shipping_status' => 'Trạng thái giao hàng',
            'payment_status' => 'Trạng thái thanh toán',
            'role_id' => 'Vai trò',
            'permissions' => 'Quyền hạn',
            'name' => 'Tên',
            'title' => 'Tiêu đề',
            'code' => 'Mã',
            'email' => 'Email',
            'reason' => 'Lý do',
        ][$field] ?? Str::headline($field);
    }

    private static function formatChangeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Đang hoạt động' : 'Tạm ẩn';
        }
        if ($value === null || $value === '') {
            return 'Không có';
        }
        if ($value === '[REDACTED]') {
            return 'Đã ẩn vì bảo mật';
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
