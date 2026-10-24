<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseResource::class;

    /**
     * رقم الدورة = التسلسل/السنة. السنة من تاريخ البداية، والتسلسل تلقائي (الأعلى في السنة + 1)
     * إلا إذا أدخله المستخدم. يعتمد على القيد الفريد في قاعدة البيانات ويعيد المحاولة عند التزامن.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $year = Carbon::parse($data['start_date'])->year;
        $data['course_year'] = $year;
        $data['created_by'] = auth()->id();
        $manual = filled($data['course_sequence'] ?? null);

        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $year, $manual) {
                    if (! $manual) {
                        $data['course_sequence'] = (int) Course::where('course_year', $year)->max('course_sequence') + 1;
                    }

                    return Course::create($data);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($manual || $attempt >= 4) {
                    throw $e;
                }
            }
        }
    }
}
