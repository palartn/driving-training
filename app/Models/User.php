<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'center_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** أعلى مستوى هرمي بين أدوار المستخدم (4 مدير عام ... 1 موظف، 0 بلا دور). */
    public function hierarchyLevel(): int
    {
        return (int) ($this->roles->max('hierarchy_level') ?? 0);
    }
}
