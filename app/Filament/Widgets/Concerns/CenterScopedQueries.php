<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentSubject;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/** استعلامات لوحة التحكم مقيَّدة بمركز المستخدم (غير المرتبط بمركز يرى الكل). */
trait CenterScopedQueries
{
    protected function centerId(): ?int
    {
        $center = auth()->user()?->center_id;

        return $center === null ? null : (int) $center;
    }

    protected function students(): Builder
    {
        return Student::query()->when($this->centerId(), fn (Builder $q, int $c) => $q->where('center_id', $c));
    }

    protected function courses(): Builder
    {
        return Course::query()->when($this->centerId(), fn (Builder $q, int $c) => $q->where('center_id', $c));
    }

    /** التسجيلات الجارية. */
    protected function activeEnrollments(): Builder
    {
        return Enrollment::query()
            ->active()
            ->when($this->centerId(), fn (Builder $q, int $c) => $q->whereHas('course', fn (Builder $q2) => $q2->where('center_id', $c)));
    }

    protected function exams(): Builder
    {
        return Exam::query()
            ->when($this->centerId(), fn (Builder $q, int $c) => $q->whereHas('course', fn (Builder $q2) => $q2->where('center_id', $c)));
    }

    /** المواد غير المسددة في تسجيلات جارية. */
    protected function unpaidUnits(): Builder
    {
        return EnrollmentSubject::query()
            ->unpaid()
            ->whereHas('enrollment', fn (Builder $q) => $q
                ->active()
                ->when($this->centerId(), fn (Builder $q2, int $c) => $q2->whereHas('course', fn (Builder $q3) => $q3->where('center_id', $c))));
    }

    /** تسجيلات جارية بعدد مواد غير مسددة يبلغ حد المنع أو يتجاوزه. */
    protected function blockedEnrollments(): Builder
    {
        $threshold = max(1, (int) config('training.unpaid_subjects_block_threshold', 2));

        return $this->activeEnrollments()
            ->whereHas('enrollmentSubjects', fn (Builder $q) => $q->unpaid(), '>=', $threshold);
    }
}
