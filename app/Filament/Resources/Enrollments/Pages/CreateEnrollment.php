<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\Course;
use App\Models\Student;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEnrollment extends CreateRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(EnrollmentService::class)->enroll(
                Student::findOrFail($data['student_id']),
                Course::findOrFail($data['course_id']),
                Carbon::parse($data['enrolled_at']),
                auth()->user(),
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title('تعذّر التسجيل')->body($e->getMessage())->persistent()->send();

            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        $course = $this->record->course;

        // المقاعد للتنبيه فقط ولا تمنع التسجيل.
        if ($course->seats !== null && $course->enrollments()->count() > $course->seats) {
            Notification::make()
                ->warning()
                ->title('تنبيه: تجاوز عدد المسجّلين المقاعد المحددة للدورة '.$course->course_number)
                ->send();
        }
    }
}
