<?php

namespace App\Domain\Shared\Repository;

use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Service\Eloquent\EloquentManager;
use App\Service\Text\CodeGenerator;
use Illuminate\Database\Eloquent\Collection;

abstract class SimpleCatalogRepository
{
    /**
     * @param class-string<SimpleCatalogModel> $modelClass
     */
    public function __construct(
        private readonly EloquentManager $eloquent,
        private readonly string $modelClass,
    ) {
    }

    /**
     * @return Collection<int, SimpleCatalogModel>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return $this->modelClass::query()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, SimpleCatalogModel>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return $this->modelClass::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?SimpleCatalogModel
    {
        $this->eloquent->boot();

        return $this->modelClass::query()->find($id);
    }

    public function findByCode(string $code): ?SimpleCatalogModel
    {
        $this->eloquent->boot();

        return $this->modelClass::query()
            ->where('code', $this->normalizeCode($code))
            ->first();
    }

    public function findByCodeExcept(string $code, int $exceptId): ?SimpleCatalogModel
    {
        $this->eloquent->boot();

        return $this->modelClass::query()
            ->where('code', $this->normalizeCode($code))
            ->where('id', '!=', $exceptId)
            ->first();
    }

    public function nextCodeFromName(string $name, string $fallback = 'catalog'): string
    {
        $this->eloquent->boot();

        $generator = new CodeGenerator();
        $base = $generator->baseFromName($name, $fallback, 80);

        for ($index = 1; $index < 1000; $index++) {
            $candidate = $generator->candidate($base, 80, $index);
            $exists = $this->modelClass::query()
                ->where('code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return $generator->candidate($base, 80, random_int(1000, 9999));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(string $code, string $name, array $attributes = []): SimpleCatalogModel
    {
        $this->eloquent->boot();

        return $this->modelClass::query()->create(array_replace([
            'code' => $this->normalizeCode($code),
            'name' => trim($name),
            'active' => true,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(SimpleCatalogModel $record, string $code, string $name, bool $active, array $attributes = []): SimpleCatalogModel
    {
        $record->code = $this->normalizeCode($code);
        $record->name = trim($name);
        $record->active = $active;

        foreach ($attributes as $field => $value) {
            $record->setAttribute($field, $value);
        }

        $record->save();

        return $record;
    }

    public function setActive(SimpleCatalogModel $record, bool $active): void
    {
        $record->active = $active;
        $record->save();
    }

    public function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
