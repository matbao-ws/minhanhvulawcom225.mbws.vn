<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasMediaUrls;

    protected $fillable = [
        'product_id',
        'image_url',
        'sort_order',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
