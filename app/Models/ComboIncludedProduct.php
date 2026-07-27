<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboIncludedProduct extends Model
{
    protected $table = 'combo_included_products';

    protected $fillable = [
        'group_id',
        'xetux_product_id',
        'xetux_item_id',
        'xetux_family_id',
        'product_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'xetux_product_id' => 'integer',
            'xetux_item_id' => 'integer',
            'xetux_family_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ComboIncludedGroup::class, 'group_id');
    }
}
