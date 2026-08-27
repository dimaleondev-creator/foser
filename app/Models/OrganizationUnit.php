<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationUnit extends Model
{
    use HasUuids;

    protected $fillable = ['parent_id', 'responsible_id', 'unit_type', 'name_fr', 'name_en', 'acronym', 'function_fr', 'function_en', 'biography_fr', 'biography_en', 'description_fr', 'professional_email', 'professional_phone', 'photo_path', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name_fr'); }
    public function childrenRecursive(): HasMany { return $this->children()->with(['responsible', 'childrenRecursive']); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_id'); }
    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }

    public function getLocalizedNameAttribute(): string { return $this->translation('name'); }
    public function getLocalizedFunctionAttribute(): ?string { return $this->translation('function'); }
    public function getLocalizedBiographyAttribute(): ?string { return $this->translation('biography'); }
    public function getPhotoUrlAttribute(): ?string { return $this->photo_path ? url('storage/' . ltrim($this->photo_path, '/')) : null; }

    private function translation(string $field): ?string
    {
        $locale = app()->getLocale();
        return $this->{$field . '_' . $locale} ?: $this->{$field . '_' . (config('app.fallback_locale', 'fr') === 'fr' ? 'en' : 'fr')};
    }
}
