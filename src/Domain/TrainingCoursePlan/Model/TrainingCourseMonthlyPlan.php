<?php

namespace App\Domain\TrainingCoursePlan\Model;

use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\Shared\Model\BaseModel;
use App\Domain\TrainingCourse\Model\TrainingCourse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TrainingCourseMonthlyPlan extends BaseModel
{
    protected $table = 'factory_department_training_course_monthly_plans';

    protected $casts = [
        'factory_department_id' => 'integer',
        'training_course_id' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
        'planned_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(FactoryDepartment::class, 'factory_department_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }
}
