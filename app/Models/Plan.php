<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'monthly_price', 'branch_limit', 'staff_limit', 'modules', 'is_active'];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:2', 'branch_limit' => 'integer', 'staff_limit' => 'integer', 'modules' => 'array', 'is_active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
