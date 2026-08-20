<?php

namespace App\Domain\Employee\Model;

use App\Domain\Shared\Model\BaseModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeePeriod extends BaseModel
{
    public const TYPE_VACATION = 'vacation';
    public const TYPE_DISMISSAL = 'dismissal';

    protected $table = 'employee_periods';

    protected $casts = [
        'employee_id' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'note' => 'string',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusKey(): string
    {
        if (!$this->active) {
            return 'deactivated';
        }

        $today = new DateTimeImmutable('today');
        $dateFrom = $this->date_from;
        $dateTo = $this->date_to;

        if ($dateFrom !== null && $dateFrom > $today) {
            return 'planned';
        }

        if ($dateFrom !== null && ($dateTo === null || $dateTo >= $today)) {
            return 'active';
        }

        return 'completed';
    }
}
