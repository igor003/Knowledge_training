<?php

namespace App\Domain\FactoryBranch\Repository;

use App\Domain\FactoryBranch\Model\FactoryBranch;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class FactoryBranchRepository extends SimpleCatalogRepository
{
    private EloquentManager $eloquent;

    public function __construct(EloquentManager $eloquent)
    {
        $this->eloquent = $eloquent;

        parent::__construct($eloquent, FactoryBranch::class);
    }

    /**
     * @return Collection<int, FactoryBranch>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return FactoryBranch::query()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, FactoryBranch>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return FactoryBranch::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }
}
