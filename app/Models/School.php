<?php

namespace App\Models;

use App\Enums\SchoolSubscriptionStatus;
use App\Enums\StudentStatus;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class School extends Model implements HasName
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'slug',
        'email',
        'phone',
        'city',
        'logo_path',
        'favicon_path',
        'stamp_signature_path',
        'price_per_student',
        'monthly_minimum_charge',
        'subscription_status',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'billing_start_date',
        'is_active',
        'ai_monthly_messages_quota',
        'ai_messages_used_this_month',
    ];

    protected $attributes = [
        'ai_monthly_messages_quota' => 50,
        'ai_messages_used_this_month' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (School $school): void {
            if (blank($school->code)) {
                $school->code = static::generateUniqueCode($school->name ?? 'School');
            }

            if (blank($school->slug) && ! blank($school->name)) {
                $baseSlug = Str::slug($school->name);
                $slug = $baseSlug ?: 'school';
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }
                $school->slug = $slug;
            }

            if (blank($school->billing_start_date)) {
                $school->billing_start_date = now()->toDateString();
            }
        });
    }

    public static function generateUniqueCode(string $name): string
    {
        $ascii = Str::ascii($name);
        $clean = preg_replace('/[^A-Za-z]/', '', $ascii);

        if (strlen($clean) < 3) {
            $clean = 'SCH';
        }

        $candidate = strtoupper(substr($clean, 0, 4));
        if (strlen($candidate) < 3) {
            $candidate = str_pad($candidate, 3, 'X');
        }

        $code = $candidate;
        $counter = 1;
        while (static::where('code', $code)->exists()) {
            $suffix = (string) $counter;
            $prefixLen = max(1, 4 - strlen($suffix));
            $code = strtoupper(substr($candidate, 0, $prefixLen).$suffix);
            $counter++;
        }

        return substr($code, 0, 4);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'subscription_status' => SchoolSubscriptionStatus::class,
            'price_per_student' => 'decimal:2',
            'monthly_minimum_charge' => 'decimal:2',
            'billing_start_date' => 'date',
            'ai_monthly_messages_quota' => 'integer',
            'ai_messages_used_this_month' => 'integer',
        ];
    }

    public function getRemainingAiMessagesAttribute(): int
    {
        $quota = (int) ($this->ai_monthly_messages_quota ?? 50);
        $used = (int) ($this->ai_messages_used_this_month ?? 0);

        return max(0, $quota - $used);
    }

    public function platformInvoices(): HasMany
    {
        return $this->hasMany(PlatformSubscriptionInvoice::class);
    }

    public function getActiveStudentsCountAttribute(): int
    {
        return $this->students()->where('status', StudentStatus::Active)->count();
    }

    public function getEstimatedMonthlyBillAttribute(): float
    {
        $calculated = (float) $this->active_students_count * (float) ($this->price_per_student ?? 1.50);
        $minimum = (float) ($this->monthly_minimum_charge ?? 300.00);

        return max($calculated, $minimum);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function feeTypes(): HasMany
    {
        return $this->hasMany(FeeType::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }
}
