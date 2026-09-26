<?php

namespace App\Models;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $fillable = ['business_id', 'name', 'code', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $tenant = app(TenantContext::class);
        $query = parent::resolveRouteBindingQuery($query, $value, $field)->where('business_id', $tenant->business()->id);
        if ($tenant->membership()->role !== 'owner') {
            $query->whereIn('id', $tenant->membership()->branches()->select('branches.id'));
        }

        return $query;
    }
}
