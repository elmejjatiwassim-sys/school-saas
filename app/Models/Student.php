<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Services\InvoiceGenerationService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Student extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'school_id',
        'guardian_id',
        'classroom_id',
        'user_id',
        'massar_code',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'registration_number',
        'photo',
        'status',
        'opening_balance',
        'monthly_tuition_fee',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'opening_balance' => 'decimal:2',
            'monthly_tuition_fee' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (blank($student->registration_number) && $student->school_id) {
                $student->registration_number = static::generateNextRegistrationNumber((int) $student->school_id);
            }
        });

        static::created(function (Student $student): void {
            if ((float) $student->opening_balance > 0) {
                app(InvoiceGenerationService::class)->createOpeningBalanceInvoice($student);
            }
        });
    }

    public static function generateNextRegistrationNumber(int $schoolId, ?string $year = null): string
    {
        $year ??= date('Y');
        $prefix = "REG-{$year}-";

        $lastStudent = static::where('school_id', $schoolId)
            ->where('registration_number', 'like', "{$prefix}%")
            ->orderByDesc('registration_number')
            ->first();

        $nextNumber = 1;
        if ($lastStudent && preg_match('/^REG-\d{4}-(\d+)$/', $lastStudent->registration_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }
}
