<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch Model
 * 
 * Represents a branch/location of the restaurant
 */
class Branch extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'branch_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'address',
        'phone',
    ];

    /**
     * Get the customers for the branch.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the products for the branch.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the combos for the branch.
     */
    public function combos()
    {
        return $this->hasMany(Combo::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the extras for the branch.
     */
    public function extras()
    {
        return $this->hasMany(Extra::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the user branch access records for the branch.
     */
    public function userBranchAccess()
    {
        return $this->hasMany(UserBranchAccess::class, 'branch_id', 'branch_id');
    }
}

