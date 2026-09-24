<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'username', 'password', 'temporary_password', 'must_change_password', 'school_id', 'role', 'student_id', 'classroom_id', 'guardian_id', 'is_active', 'is_super_admin', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function getGuardianRecord(): ?Guardian
    {
        if ($this->guardian_id && $this->guardian) {
            return $this->guardian;
        }

        if ($this->school_id) {
            return Guardian::where('school_id', $this->school_id)
                ->where(function ($query) {
                    if ($this->email) {
                        $query->where('email', $this->email);
                    }
                    if ($this->phone) {
                        $query->orWhere('phone', $this->phone);
                    }
                })
                ->first();
        }

        return null;
    }

    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class, 'teacher_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! ($this->is_active ?? true)) {
            return false;
        }

        if ($panel->getId() === 'super-admin') {
            return (bool) $this->is_super_admin;
        }

        return true;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return (int) $this->school_id === (int) $tenant->id;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->school ? collect([$this->school]) : collect();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->school;
    }

    public function recordedAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'recorded_by_id');
    }

    public function reviewedAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'reviewed_by_id');
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isSupervisor(): bool
    {
        return in_array($this->role, ['supervisor', 'general_supervisor', 'surveillant_general']);
    }

    public function isGeneralSupervisor(): bool
    {
        return $this->isSupervisor();
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']) || (bool) $this->is_super_admin;
    }
}
