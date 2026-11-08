<?php

namespace Tests\Support;

use App\Models\Course;
use App\Models\CourseSubjectInstructor;
use App\Models\Instructor;
use App\Models\Subject;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionService;

trait TrainingSessionFixtures
{
    /** @param array<int, \App\Models\Center> $centers المراكز التي يدرّس فيها المدرب */
    protected function makeInstructor(array $centers, string $name = 'مدرب', bool $active = true): Instructor
    {
        $instructor = Instructor::create([
            'national_id' => str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'name' => $name,
            'phone' => '0599111111',
            'is_active' => $active,
        ]);

        $instructor->centers()->attach(collect($centers)->pluck('id')->all());

        return $instructor;
    }

    protected function assignInstructor(Course $course, Subject $subject, Instructor $instructor): CourseSubjectInstructor
    {
        return CourseSubjectInstructor::create([
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    protected function sessionData(Course $course, Subject $subject, ?Instructor $instructor = null, array $overrides = []): array
    {
        return array_merge([
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'instructor_id' => $instructor?->id,
            'session_date' => today()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '10:30',
            'type' => 'theory',
            'location' => 'قاعة 1',
        ], $overrides);
    }

    protected function makeSession(Course $course, Subject $subject, User $actor, ?Instructor $instructor = null, array $overrides = []): TrainingSession
    {
        return app(SessionService::class)->create($this->sessionData($course, $subject, $instructor, $overrides), $actor);
    }
}
