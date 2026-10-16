<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Product Model
 * 
 * Represents a product/item on the menu
 */
class Product extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'product_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price_eur',
        'description',
        'is_active',
        'branch_id',
        'image_url',
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
        ];
    }

    /**
     * Get the branch that owns the product.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id', 'product_id');
    }

    /**
     * Get the extras associated with the product.
     */
    public function extras()
    {
        return $this->belongsToMany(Extra::class, 'product_extra', 'product_id', 'extra_id')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }
}

