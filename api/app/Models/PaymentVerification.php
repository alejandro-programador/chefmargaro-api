<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PaymentVerification Model
 * 
 * Represents a verification of a payment by a user
 */
class PaymentVerification extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'verification_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_id',
        'verifier_id',
        'verification_status',
        'verification_date',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_date' => 'datetime',
        ];
    }

    /**
     * Get the payment that owns the verification.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    /**
     * Get the user (verifier) that created the verification.
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verifier_id', 'user_id');
    }
}

