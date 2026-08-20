<?php

namespace App\Domain\TrainingCourse\Repository;

use App\Domain\TrainingCourse\Model\TrainingCourse;
use App\Service\Eloquent\EloquentManager;
use App\Service\Text\CodeGenerator;
use Illuminate\Database\Eloquent\Collection;

final class TrainingCourseRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, TrainingCourse>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return TrainingCourse::query()
            ->with([
                'departments' => static function ($query): void {
                    $query->orderBy('sort_order')->orderBy('name')->orderBy('factory_departments.id');
                },
                'type',
                'assessmentMethod',
            ])
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, TrainingCourse> */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return TrainingCourse::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?TrainingCourse
    {
        $this->eloquent->boot();

        return TrainingCourse::query()->find($id);
    }

    /**
     * @param array<int, int> $departmentIds
     */
    public function create(
        string $name,
        ?string $description,
        ?float $durationHours,
        ?int $periodicityMonths,
        int $assessmentMethodId,
        string $assessmentMethodCode,
        array $departmentIds,
        ?int $typeId,
    ): TrainingCourse {
        $this->eloquent->boot();

        return (new TrainingCourse())->getConnection()->transaction(function () use ($name, $description, $durationHours, $periodicityMonths, $assessmentMethodId, $assessmentMethodCode, $departmentIds, $typeId): TrainingCourse {
            $course = TrainingCourse::query()->create([
                'code' => $this->nextCodeFromName($name),
                'name' => trim($name),
                'description' => $description,
                'training_course_type_id' => $typeId,
                'training_course_assessment_method_id' => $assessmentMethodId,
                'duration_hours' => $durationHours,
                'periodicity_months' => $periodicityMonths,
                'assessment_method' => $assessmentMethodCode,
                'active' => true,
            ]);

            $this->syncDepartments($course, $departmentIds);
            $course->load('type', 'assessmentMethod');

            return $course;
        });
    }

    /**
     * @param array<int, int> $departmentIds
     */
    public function update(
        TrainingCourse $course,
        string $name,
        ?string $description,
        ?float $durationHours,
        ?int $periodicityMonths,
        int $assessmentMethodId,
        string $assessmentMethodCode,
        bool $active,
        array $departmentIds,
        ?int $typeId,
    ): TrainingCourse {
        $this->eloquent->boot();

        return $course->getConnection()->transaction(function () use ($course, $name, $description, $durationHours, $periodicityMonths, $assessmentMethodId, $assessmentMethodCode, $active, $departmentIds, $typeId): TrainingCourse {
            $course->name = trim($name);
            $course->description = $description;
            $course->training_course_type_id = $typeId;
            $course->training_course_assessment_method_id = $assessmentMethodId;
            $course->duration_hours = $durationHours;
            $course->periodicity_months = $periodicityMonths;
            $course->assessment_method = $assessmentMethodCode;
            $course->active = $active;
            $course->save();

            $this->syncDepartments($course, $departmentIds);
            $course->load('type', 'assessmentMethod');

            return $course;
        });
    }

    public function setActive(TrainingCourse $course, bool $active): void
    {
        $course->active = $active;
        $course->save();
    }

    /**
     * @param array<int, int> $departmentIds
     */
    public function syncDepartments(TrainingCourse $course, array $departmentIds): void
    {
        $ids = [];
        foreach ($departmentIds as $departmentId) {
            $id = (int) $departmentId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $course->departments()->sync(array_values($ids));
        $course->load(['departments' => static function ($query): void {
            $query->orderBy('sort_order')->orderBy('name')->orderBy('factory_departments.id');
        }]);
    }

    private function nextCodeFromName(string $name): string
    {
        $generator = new CodeGenerator();
        $base = $generator->baseFromName($name, 'training_course', 80);

        for ($index = 1; $index < 1000; $index++) {
            $candidate = $generator->candidate($base, 80, $index);
            $exists = TrainingCourse::query()
                ->where('code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return $generator->candidate($base, 80, random_int(1000, 9999));
    }
}
