<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    use HasFactory;

    /**
     * `is_system` is guarded — it is the flag the CRUD checks before
     * allowing a delete, and a source that could mark itself
     * non-system through a form payload would be no protection at all.
     */
    protected $fillable = [
        'key',
        'label',
        'is_active',
        'sort_order',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_system' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('key');
    }

    /**
     * Leads that resolved to this source.
     *
     * NOT an Eloquent relation: leads.source_platform stores this row's
     * KEY as a plain string rather than a foreign key, because a lead can
     * also carry a raw utm_source that matches no configured source at
     * all.
     */
    public function leadsCount(): int
    {
        return Lead::where('source_platform', $this->key)->count();
    }
}
