<?php

namespace App\Domain\TrainingCourse\Repository;

use App\Domain\TrainingCourse\Model\TrainingCourseAssessmentMethod;
use App\Service\Eloquent\EloquentManager;
use App\Service\Text\CodeGenerator;
use Illuminate\Database\Eloquent\Collection;

final class TrainingCourseAssessmentMethodRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, TrainingCourseAssessmentMethod>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return TrainingCourseAssessmentMethod::query()
            ->withCount('courses')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, TrainingCourseAssessmentMethod>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return TrainingCourseAssessmentMethod::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?TrainingCourseAssessmentMethod
    {
        $this->eloquent->boot();

        return TrainingCourseAssessmentMethod::query()->find($id);
    }

    public function create(string $name, ?string $description): TrainingCourseAssessmentMethod
    {
        $this->eloquent->boot();

        return TrainingCourseAssessmentMethod::query()->create([
            'code' => $this->nextCodeFromName($name),
            'name' => trim($name),
            'description' => $description,
            'active' => true,
        ]);
    }

    public function update(TrainingCourseAssessmentMethod $method, string $name, ?string $description): TrainingCourseAssessmentMethod
    {
        $method->name = trim($name);
        $method->description = $description;
        $method->save();

        return $method;
    }

    public function setActive(TrainingCourseAssessmentMethod $method, bool $active): void
    {
        $method->active = $active;
        $method->save();
    }

    private function nextCodeFromName(string $name): string
    {
        $generator = new CodeGenerator();
        $base = $generator->baseFromName($name, 'training_course_assessment_method', 80);

        for ($index = 1; $index < 1000; $index++) {
            $candidate = $generator->candidate($base, 80, $index);
            $exists = TrainingCourseAssessmentMethod::query()
                ->where('code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return $generator->candidate($base, 80, random_int(1000, 9999));
    }
}
