<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Extra Model
 * 
 * Represents an extra/add-on that can be added to products or combos
 */
class Extra extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'extra_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'title',
        'description',
        'image_url',
        'price_eur',
        'quantity',
        'is_active',
        'xetux_product_id',
        'xetux_item_id',
        'xetux_family_id',
    ];

    /**
     * Sabores del catálogo cuando el extra representa una familia de bebidas.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $flavorOptions = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_eur' => 'decimal:2',
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the branch that owns the extra.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the products that have this extra.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_extra', 'extra_id', 'product_id')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    /**
     * Get the combos that have this extra.
     */
    public function combos()
    {
        return $this->belongsToMany(Combo::class, 'combo_extra', 'extra_id', 'combo_id')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    /**
     * Extra de bebida por familia: el producto Xetux se elige al pedir el sabor.
     */
    public function isDrinkFamily(): bool
    {
        if ($this->xetux_product_id) {
            return false;
        }

        $familyId = (int) $this->xetux_family_id;

        return $familyId > 0 && array_key_exists($familyId, config('xetux.drink_extra_families', []));
    }
}

