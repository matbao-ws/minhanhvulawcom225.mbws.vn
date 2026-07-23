<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use DomainException;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::query()
            ->withCount('targets')
            ->when(request('q'), fn ($query, $keyword) => $query->where('name', 'like', "%{$keyword}%"))
            ->when(request()->filled('kind'), fn ($query) => $query->where('kind', request('kind')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.promotions.index', compact('promotions'));
    }

    public function create()
    {
        return view('admin.promotions.create', [
            'promotion' => new Promotion([
                'kind' => 'automatic',
                'applies_to' => 'selected',
                'discount_type' => 'percentage',
                'min_quantity' => 1,
                'priority' => 0,
                'is_active' => true,
            ]),
            ...$this->targetOptions(),
        ]);
    }

    public function store(PromotionRequest $request)
    {
        $data = $request->validated();
        try {
            $promotion = DB::transaction(function () use ($data) {
                $promotion = Promotion::query()->create($this->payload($data));
                $this->syncTargets($promotion, $data);

                return $promotion;
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['targets' => $exception->getMessage()]);
        }

        return redirect()->route('admin.promotions.edit', $promotion)->with('success', 'Đã tạo chương trình khuyến mãi.');
    }

    public function edit(string $locale, Promotion $promotion)
    {
        return view('admin.promotions.edit', [
            'promotion' => $promotion->load('targets'),
            ...$this->targetOptions(),
        ]);
    }

    public function update(PromotionRequest $request, string $locale, Promotion $promotion)
    {
        $data = $request->validated();
        try {
            DB::transaction(function () use ($promotion, $data): void {
                $promotion->update($this->payload($data, $promotion));
                $this->syncTargets($promotion, $data);
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['targets' => $exception->getMessage()]);
        }

        return redirect()->route('admin.promotions.edit', $promotion)->with('success', 'Đã cập nhật chương trình khuyến mãi.');
    }

    public function destroy(string $locale, Promotion $promotion)
    {
        if ($promotion->used_count > 0) {
            return back()->withErrors(['promotion' => 'Không thể xóa chương trình đã có lượt sử dụng. Hãy tắt chương trình thay vì xóa.']);
        }

        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', 'Đã xóa chương trình khuyến mãi.');
    }

    private function payload(array $data, ?Promotion $promotion = null): array
    {
        $name = $promotion?->getTranslations('name') ?? [];
        $description = $promotion?->getTranslations('description') ?? [];
        $name = [...$name, ...$this->translations($data['name'])];
        $description = [...$description, ...$this->translations($data['description'] ?? [])];

        return [
            'name' => $name,
            'description' => $description ?: null,
            'kind' => $data['kind'],
            'applies_to' => $data['applies_to'],
            'discount_type' => $data['discount_type'],
            'value' => $data['value'],
            'min_quantity' => $data['min_quantity'] ?? 1,
            'quantity_limit' => $data['quantity_limit'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'is_stackable' => (bool) ($data['is_stackable'] ?? false),
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function syncTargets(Promotion $promotion, array $data): void
    {
        $specs = [];
        if ($data['applies_to'] === 'selected') {
            foreach ($data['target_product_ids'] ?? [] as $productId) {
                $specs["{$productId}:all"] = ['product_id' => (int) $productId, 'product_variant_id' => null];
            }
            $variants = ProductVariant::query()->whereIn('id', $data['target_variant_ids'] ?? [])->get(['id', 'product_id']);
            if ($variants->count() !== count($data['target_variant_ids'] ?? [])) {
                throw new DomainException('Có SKU không tồn tại.');
            }
            foreach ($variants as $variant) {
                $specs["{$variant->product_id}:{$variant->id}"] = ['product_id' => $variant->product_id, 'product_variant_id' => $variant->id];
            }
        }

        $existing = $promotion->targets()->get()->keyBy(fn (PromotionTarget $target) => "{$target->product_id}:".($target->product_variant_id ?: 'all'));
        foreach ($specs as $key => $spec) {
            $target = $existing->pull($key) ?: new PromotionTarget(['promotion_id' => $promotion->id]);
            $target->fill($spec + ['quantity_limit' => $data['target_quantity_limit'] ?? null])->save();
        }

        foreach ($existing as $target) {
            if ($target->used_count > 0) {
                throw new DomainException('Không thể bỏ một SKU đã có lượt flash sale. Hãy tắt chương trình hoặc giữ SKU đó.');
            }
            $target->delete();
        }
    }

    private function targetOptions(): array
    {
        return [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
            'variants' => ProductVariant::query()->where('is_active', true)->with('product:id,name')->orderBy('sku')->get(['id', 'product_id', 'name', 'sku']),
        ];
    }

    private function translations(array $values): array
    {
        $languages = app(\App\Services\LanguageRegistry::class);
        return collect($values)->filter(fn ($value, $locale) => $languages->supports((string) $locale) && is_string($value) && trim($value) !== '')->map(fn (string $value) => trim($value))->all();
    }
}
