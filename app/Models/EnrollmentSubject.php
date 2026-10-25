<?php

namespace App\Models;

use App\Enums\FinancialStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EnrollmentSubject extends Model
{
    protected $fillable = [
        'enrollment_id',
        'subject_id',
        'tariff_id',
        'lectures_count',
        'price_amount',
    ];

    protected function casts(): array
    {
        return ['price_amount' => 'decimal:2'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(SubjectTariff::class, 'tariff_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function exemption(): HasOne
    {
        return $this->hasOne(Exemption::class);
    }

    /**
     * المواد غير المسددة بصيغة استعلام (للوحة التحكم والتقارير)، وتطابق منطق financialStatus():
     * لا دفعة، ولا إعفاء كامل، وسعر المادة أكبر من صفر.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query
            ->where('price_amount', '>', 0)
            ->whereDoesntHave('payment')
            ->whereDoesntHave('exemption', fn (Builder $q) => $q->whereColumn('exemptions.exemption_amount', '>=', 'enrollment_subjects.price_amount'));
    }

    /** المستحق بعد الإعفاء، بالقروش/الأغورة لتفادي أخطاء الكسور. */
    public function dueCents(): int
    {
        $price = (int) round(((float) $this->price_amount) * 100);
        $exempt = $this->exemption
            ? (int) round(((float) $this->exemption->exemption_amount) * 100)
            : 0;

        return max(0, $price - $exempt);
    }

    /** الحالة المالية محسوبة وغير مخزّنة: معافى / مسدد / غير مسدد. */
    public function financialStatus(): FinancialStatus
    {
        if ($this->dueCents() === 0) {
            return FinancialStatus::Exempt;
        }

        return $this->payment ? FinancialStatus::Paid : FinancialStatus::Unpaid;
    }
}
