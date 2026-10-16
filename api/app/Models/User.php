<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User Model
 * 
 * Represents a system user (admin, verifier, etc.)
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'name',
        'user_type',
        'role_id',
        'branch_id',
        'password_hash',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login' => 'datetime',
            'password' => 'hashed',
            'password_hash' => 'hashed',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(UserRole::class, 'role_id', 'role_id');
    }

    /**
     * Get the branch assigned to the user (for admin/verificador scope).
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the user branch access records for the user.
     */
    public function userBranchAccess()
    {
        return $this->hasMany(UserBranchAccess::class, 'user_id', 'user_id');
    }

    /**
     * Get the payment verifications created by the user.
     */
    public function paymentVerifications()
    {
        return $this->hasMany(PaymentVerification::class, 'verifier_id', 'user_id');
    }

    /**
     * Get the logs created by the user.
     */
    public function logs()
    {
        return $this->hasMany(Log::class, 'user_id', 'user_id');
    }
}
