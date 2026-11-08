<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CourseSubjectInstructor;
use App\Models\Instructor;
use App\Models\TrainingSession;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\Support\TrainingSessionFixtures;
use Tests\TestCase;

class InstructorModuleTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;
    use TrainingSessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_instructor_national_id_must_be_nine_digits(): void
    {
        $this->expectException(QueryException::class);

        Instructor::create(['national_id' => '12345', 'name' => 'مدرب', 'phone' => '0599111111']);
    }

    public function test_instructor_national_id_is_unique(): void
    {
        $first = $this->makeInstructor([$this->makeCenter()]);

        $this->expectException(UniqueConstraintViolationException::class);

        Instructor::create(['national_id' => $first->national_id, 'name' => 'آخر', 'phone' => '0599111112']);
    }

    public function test_an_instructor_can_teach_in_more_than_one_center(): void
    {
        $instructor = $this->makeInstructor([$this->makeCenter('A'), $this->makeCenter('B')]);

        $this->assertCount(2, $instructor->fresh()->centers);
    }

    public function test_an_instructor_cannot_be_linked_twice_to_the_same_center(): void
    {
        $center = $this->makeCenter();
        $instructor = $this->makeInstructor([$center]);

        $this->expectException(UniqueConstraintViolationException::class);

        $instructor->centers()->attach($center->id);
    }

    public function test_assignment_requires_a_subject_that_belongs_to_the_course(): void
    {
        $center = $this->makeCenter();
        $inCourse = $this->makeSubject('داخل الدورة');
        $outside = $this->makeSubject('خارج الدورة');
        $course = $this->makeCourse($center, [$inCourse]);
        $instructor = $this->makeInstructor([$center]);

        $this->expectException(QueryException::class);

        CourseSubjectInstructor::create([
            'course_id' => $course->id,
            'subject_id' => $outside->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_a_session_must_be_for_a_subject_of_the_course(): void
    {
        $center = $this->makeCenter();
        $course = $this->makeCourse($center, [$this->makeSubject('داخل الدورة')]);
        $outside = $this->makeSubject('خارج الدورة');

        $this->expectException(QueryException::class);

        TrainingSession::create([
            'course_id' => $course->id,
            'subject_id' => $outside->id,
            'session_date' => today()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '10:00:00',
            'type' => 'theory',
        ]);
    }

    public function test_session_end_must_be_after_its_start(): void
    {
        $center = $this->makeCenter();
        $subject = $this->makeSubject('مادة');
        $course = $this->makeCourse($center, [$subject]);

        $this->expectException(QueryException::class);

        TrainingSession::create([
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'session_date' => today()->toDateString(),
            'starts_at' => '10:00:00',
            'ends_at' => '09:00:00',
            'type' => 'theory',
        ]);
    }

    public function test_session_duration_is_generated_in_minutes(): void
    {
        $center = $this->makeCenter();
        $subject = $this->makeSubject('مادة');
        $course = $this->makeCourse($center, [$subject]);

        $session = TrainingSession::create([
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'session_date' => today()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '10:30:00',
            'type' => 'theory',
        ]);

        $this->assertSame(90, (int) $session->fresh()->duration_minutes);
    }

    public function test_attendance_is_unique_per_session_and_enrollment(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('مادة');
        $course = $this->makeCourse($center, [$subject]);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);
        $session = $this->makeSession($course, $subject, $head);

        $row = ['training_session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'present', 'recorded_by' => $employee->id];

        AttendanceRecord::create($row);

        $this->expectException(UniqueConstraintViolationException::class);

        AttendanceRecord::create($row);
    }
}
