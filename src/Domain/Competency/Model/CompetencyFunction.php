<?php

namespace App\Domain\Competency\Model;

use App\Domain\Department\Model\Department;
use App\Domain\Position\Model\Position;
use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompetencyFunction extends BaseModel
{
    protected $table = 'competency_function';

    protected $casts = [
        'competency_id' => 'integer',
        'factory_section_id' => 'integer',
        'factory_function_id' => 'integer',
        'critical' => 'boolean',
    ];

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'factory_section_id');
    }

    public function function(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'factory_function_id');
    }
}
