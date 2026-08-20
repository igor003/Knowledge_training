<?php

namespace App\Domain\TrainingCoursePlan\Repository;

use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\TrainingCoursePlan\Model\TrainingCourseMonthlyPlan;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class TrainingCoursePlanRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, FactoryDepartment>
     */
    public function listActiveDepartmentsWithCourses(): Collection
    {
        $this->eloquent->boot();

        return FactoryDepartment::query()
            ->where('active', true)
            ->whereHas('trainingCourses', static function ($query): void {
                $query->where('training_courses.active', true);
            })
            ->with(['trainingCourses' => static function ($query): void {
                $query->where('training_courses.active', true)->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function firstDepartmentWithCourses(): ?FactoryDepartment
    {
        return $this->listActiveDepartmentsWithCourses()->first();
    }

    public function findDepartmentWithCourses(int $departmentId): ?FactoryDepartment
    {
        $this->eloquent->boot();

        return FactoryDepartment::query()
            ->where('active', true)
            ->where('id', $departmentId)
            ->with(['trainingCourses' => static function ($query): void {
                $query->where('training_courses.active', true)->orderBy('name');
            }])
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function matrixRowsForDepartment(FactoryDepartment $department, int $year): array
    {
        return $this->matrixRowsForDepartments([$department], $year);
    }

    /**
     * @param iterable<int, FactoryDepartment> $departments
     * @return array<int, array<string, mixed>>
     */
    public function matrixRowsForDepartments(iterable $departments, int $year): array
    {
        $rows = [];

        foreach ($departments as $department) {
            array_push($rows, ...$this->matrixRowsForSingleDepartment($department, $year));
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matrixRowsForSingleDepartment(FactoryDepartment $department, int $year): array
    {
        $department->loadMissing(['trainingCourses' => static function ($query): void {
            $query->where('training_courses.active', true)->orderBy('name');
        }]);

        $plans = $this->plansForDepartment($department, $year);
        $rows = [];

        foreach ($department->trainingCourses as $course) {
            $courseId = (int) $course->id;
            $months = [];
            $yearPlan = 0;
            $yearDone = 0;

            for ($month = 1; $month <= 12; $month++) {
                $plan = $plans[$courseId][$month] ?? null;
                $planned = $plan instanceof TrainingCourseMonthlyPlan ? (int) $plan->planned_count : 0;
                $completed = $this->completedCountFor((int) $department->id, $courseId, $year, $month);

                $months[$month] = [
                    'planned' => $planned,
                    'completed' => $completed,
                    'percent' => $this->percent($completed, $planned),
                ];

                $yearPlan += $planned;
                $yearDone += $completed;
            }

            if ($yearPlan <= 0) {
                continue;
            }

            $rows[] = [
                'department' => $department,
                'is_department_first' => false,
                'department_rowspan' => 1,
                'course' => $course,
                'months' => $months,
                'planned_total' => $yearPlan,
                'completed_total' => $yearDone,
                'percent_total' => $this->percent($yearDone, $yearPlan),
            ];
        }

        $courseCount = count($rows);
        foreach ($rows as $index => $row) {
            $rows[$index]['is_department_first'] = $index === 0;
            $rows[$index]['department_rowspan'] = $courseCount;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForDepartment(FactoryDepartment $department, int $year): array
    {
        return $this->snapshotForDepartments([$department], $year, $department->label());
    }

    /**
     * @param iterable<int, FactoryDepartment> $departments
     * @return array<string, mixed>
     */
    public function snapshotForDepartments(iterable $departments, int $year, string $label): array
    {
        return [
            'scope' => $label,
            'year' => $year,
            'rows' => array_map(static function (array $row): array {
                return [
                    'factory_department_id' => (int) $row['department']->id,
                    'department' => $row['department']->label(),
                    'course_id' => (int) $row['course']->id,
                    'course' => $row['course']->label(),
                    'planned_total' => $row['planned_total'],
                    'completed_total' => $row['completed_total'],
                    'percent_total' => $row['percent_total'],
                    'months' => $row['months'],
                ];
            }, $this->matrixRowsForDepartments($departments, $year)),
        ];
    }

    /**
     * @param array<int, array<int, array<int, array{planned: int}>>> $values
     */
    public function saveDepartmentYear(FactoryDepartment $department, int $year, array $values): void
    {
        $this->saveDepartmentsYear([$department], $year, [(int) $department->id => $values[(int) $department->id] ?? []]);
    }

    /**
     * @param iterable<int, FactoryDepartment> $departments
     * @param array<int, array<int, array<int, array{planned: int}>>> $values
     */
    public function saveDepartmentsYear(iterable $departments, int $year, array $values): void
    {
        $this->eloquent->boot();

        (new TrainingCourseMonthlyPlan())->getConnection()->transaction(function () use ($departments, $year, $values): void {
            foreach ($departments as $department) {
                $this->saveSingleDepartmentYear($department, $year, $values[(int) $department->id] ?? []);
            }
        });
    }

    public function deleteDepartmentPlans(FactoryDepartment $department): void
    {
        $this->eloquent->boot();

        TrainingCourseMonthlyPlan::query()
            ->where('factory_department_id', (int) $department->id)
            ->delete();
    }

    /**
     * @return array<int, array<int, TrainingCourseMonthlyPlan>>
     */
    private function plansForDepartment(FactoryDepartment $department, int $year): array
    {
        $this->eloquent->boot();
        $plans = [];

        TrainingCourseMonthlyPlan::query()
            ->where('factory_department_id', (int) $department->id)
            ->where('year', $year)
            ->get()
            ->each(static function (TrainingCourseMonthlyPlan $plan) use (&$plans): void {
                $plans[(int) $plan->training_course_id][(int) $plan->month] = $plan;
            });

        return $plans;
    }

    /**
     * @param array<int, array<int, array{planned: int}>> $values
     */
    private function saveSingleDepartmentYear(FactoryDepartment $department, int $year, array $values): void
    {
        $department->loadMissing(['trainingCourses' => static function ($query): void {
            $query->where('training_courses.active', true)->orderBy('name');
        }]);

        $courseIds = $department->trainingCourses
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $allowedCourseIds = array_fill_keys($courseIds, true);

        foreach ($values as $courseId => $months) {
            $courseId = (int) $courseId;
            if (!isset($allowedCourseIds[$courseId])) {
                continue;
            }

            for ($month = 1; $month <= 12; $month++) {
                $planned = max(0, (int) ($months[$month]['planned'] ?? 0));

                if ($planned === 0) {
                    TrainingCourseMonthlyPlan::query()
                        ->where('factory_department_id', (int) $department->id)
                        ->where('training_course_id', $courseId)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->delete();

                    continue;
                }

                TrainingCourseMonthlyPlan::query()->updateOrCreate(
                    [
                        'factory_department_id' => (int) $department->id,
                        'training_course_id' => $courseId,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'planned_count' => $planned,
                    ],
                );
            }
        }
    }

    private function completedCountFor(int $departmentId, int $courseId, int $year, int $month): int
    {
        return 0;
    }

    private function percent(int $completed, int $planned): ?int
    {
        if ($planned <= 0) {
            return null;
        }

        return (int) round(($completed / $planned) * 100);
    }
}
