<?php

namespace App\Domain\FunctionType\Repository;

use App\Domain\FunctionType\Model\FunctionType;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class FunctionTypeRepository extends SimpleCatalogRepository
{
    private EloquentManager $eloquent;

    public function __construct(EloquentManager $eloquent)
    {
        $this->eloquent = $eloquent;
        parent::__construct($eloquent, FunctionType::class);
    }

    /** @return Collection<int, FunctionType> */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return FunctionType::query()
            ->withCount('functions')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }
}
