<?php

namespace App\Domain\Audit\Repository;

use App\Domain\Audit\Model\AuditLog;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class AuditLogRepository
{
    private const DEFAULT_LIMIT = 1000;

    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function latest(int $limit = self::DEFAULT_LIMIT): Collection
    {
        $this->eloquent->boot();

        return AuditLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
