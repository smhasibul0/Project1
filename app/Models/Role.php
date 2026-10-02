<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The Admin (super) role always has every permission and is never restricted.
     */
    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    public function hasPermission(string $key): bool
    {
        return $this->isAdmin() || $this->permissions->contains('key', $key);
    }
}
