<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComboIncludedGroup extends Model
{
    protected $table = 'combo_included_groups';

    protected $fillable = [
        'combo_id',
        'type',
        'name',
        'max_quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'combo_id', 'combo_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ComboIncludedProduct::class, 'group_id')
            ->orderBy('sort_order');
    }
}
