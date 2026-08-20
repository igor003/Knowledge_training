<?php

namespace App\Domain\TrainingCourse\Repository;

use App\Domain\TrainingCourse\Model\TrainingCourseType;
use App\Service\Eloquent\EloquentManager;
use App\Service\Text\CodeGenerator;
use Illuminate\Database\Eloquent\Collection;

final class TrainingCourseTypeRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, TrainingCourseType>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return TrainingCourseType::query()
            ->withCount('courses')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, TrainingCourseType>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return TrainingCourseType::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?TrainingCourseType
    {
        $this->eloquent->boot();

        return TrainingCourseType::query()->find($id);
    }

    public function create(string $name, ?string $description): TrainingCourseType
    {
        $this->eloquent->boot();

        return TrainingCourseType::query()->create([
            'code' => $this->nextCodeFromName($name),
            'name' => trim($name),
            'description' => $description,
            'active' => true,
        ]);
    }

    public function update(TrainingCourseType $type, string $name, ?string $description): TrainingCourseType
    {
        $type->name = trim($name);
        $type->description = $description;
        $type->save();

        return $type;
    }

    public function setActive(TrainingCourseType $type, bool $active): void
    {
        $type->active = $active;
        $type->save();
    }

    public function physicallyDelete(TrainingCourseType $type): void
    {
        $this->eloquent->boot();

        $type->getConnection()->transaction(function () use ($type): void {
            $type->courses()->update(['training_course_type_id' => null]);
            $type->getConnection()
                ->table('training_course_type_training_course')
                ->where('training_course_type_id', (int) $type->id)
                ->delete();
            $type->delete();
        });
    }

    private function nextCodeFromName(string $name): string
    {
        $generator = new CodeGenerator();
        $base = $generator->baseFromName($name, 'training_course_type', 80);

        for ($index = 1; $index < 1000; $index++) {
            $candidate = $generator->candidate($base, 80, $index);
            $exists = TrainingCourseType::query()
                ->where('code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return $generator->candidate($base, 80, random_int(1000, 9999));
    }
}
