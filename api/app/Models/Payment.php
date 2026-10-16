<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment Model
 * 
 * Represents a payment for an order
 */
class Payment extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'payment_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'branch_id',
        'payment_method',
        'payment_status',
        'payment_date',
        'proof_image_url',
        'reference_number',
        'payment_reference_number',
        'status_view_token',
        'reported_amount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
            'reported_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the order that owns the payment.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    /**
     * Get the branch this payment belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the payment verifications for the payment.
     */
    public function verifications()
    {
        return $this->hasMany(PaymentVerification::class, 'payment_id', 'payment_id');
    }
}

