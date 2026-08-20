<?php

namespace App\Domain\FactoryDepartment\Model;

use App\Domain\Department\Model\Department;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\TrainingCourse\Model\TrainingCourse;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FactoryDepartment extends SimpleCatalogModel
{
    protected $table = 'factory_departments';

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Department::class, 'factory_department_id');
    }

    public function trainingCourses(): BelongsToMany
    {
        return $this->belongsToMany(TrainingCourse::class, 'factory_department_training_course', 'factory_department_id', 'training_course_id')
            ->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function departmentIds(): array
    {
        return $this->sections
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    public function trainingCourseIds(): array
    {
        return $this->trainingCourses
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, string> */
    public function trainingCourseLabels(): array
    {
        return $this->trainingCourses
            ->map(static fn (TrainingCourse $course): string => $course->label())
            ->all();
    }
}
