<?php

namespace App\Domain\Competency\Repository;

use App\Domain\Competency\Model\Competency;
use App\Domain\Competency\Model\CompetencyFunction;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class CompetencyRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /** @return Collection<int, Competency> */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return Competency::query()
            ->with('functionAssignments')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Competency> */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return Competency::query()->where('active', true)->orderBy('name')->get();
    }

    /** @param array<int, array{factory_section_id:int, competency_id:int, critical:bool}> $assignments */
    public function syncFunctionAssignments(int $functionId, array $assignments): void
    {
        $this->eloquent->boot();

        CompetencyFunction::query()->where('factory_function_id', $functionId)->delete();
        foreach ($assignments as $assignment) {
            CompetencyFunction::query()->create([
                'competency_id' => $assignment['competency_id'],
                'factory_section_id' => $assignment['factory_section_id'],
                'factory_function_id' => $functionId,
                'critical' => $assignment['critical'],
            ]);
        }
    }

    public function findById(int $id): ?Competency
    {
        $this->eloquent->boot();

        return Competency::query()->with('functionAssignments')->find($id);
    }

    public function create(array $attributes): Competency
    {
        $this->eloquent->boot();

        return Competency::query()->create($attributes);
    }

    /** @param array<int, array{factory_section_id:int, factory_function_id:int, critical:bool}> $pairs */
    public function syncFunctionPairs(Competency $competency, array $pairs): void
    {
        $competency->functionAssignments()->delete();
        foreach ($pairs as $pair) {
            CompetencyFunction::query()->create([
                'competency_id' => $competency->id,
                'factory_section_id' => $pair['factory_section_id'],
                'factory_function_id' => $pair['factory_function_id'],
                'critical' => $pair['critical'],
            ]);
        }
        $competency->load('functionAssignments');
    }

    public function update(Competency $competency, array $attributes): Competency
    {
        $competency->fill($attributes);
        $competency->save();

        return $competency;
    }

    public function setActive(Competency $competency, bool $active): void
    {
        $competency->active = $active;
        $competency->save();
    }

    public function physicallyDelete(Competency $competency): void
    {
        $this->eloquent->boot();

        $competency->getConnection()->transaction(function () use ($competency): void {
            $competency->functionAssignments()->delete();
            $competency->delete();
        });
    }

    public function existsByName(string $name, ?int $exceptId = null): bool
    {
        $query = Competency::query()->where('name', trim($name));

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
