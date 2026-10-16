<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Combo Model
 * 
 * Represents a combo/meal deal
 */
class Combo extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'combo_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price_eur',
        'description',
        'category',
        'rolls_count',
        'includes_drink',
        'has_topping',
        'is_active',
        'branch_id',
        'image_url',
        'xetux_product_id',
        'xetux_item_id',
        'xetux_family_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_eur' => 'decimal:2',
            'is_active' => 'boolean',
            'includes_drink' => 'boolean',
            'has_topping' => 'boolean',
        ];
    }

    /**
     * Get the branch that owns the combo.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the order items for the combo.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'combo_id', 'combo_id');
    }

    /**
     * Get the extras associated with the combo.
     */
    public function extras()
    {
        return $this->belongsToMany(Extra::class, 'combo_extra', 'combo_id', 'extra_id')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    /**
     * Grupos de productos incluidos (sin costo) asociados al combo.
     */
    public function includedGroups()
    {
        return $this->hasMany(ComboIncludedGroup::class, 'combo_id', 'combo_id')
            ->orderBy('sort_order');
    }
}

