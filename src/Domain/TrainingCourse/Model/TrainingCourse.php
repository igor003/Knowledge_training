<?php

namespace App\Domain\TrainingCourse\Model;

use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class TrainingCourse extends BaseModel
{
    public const ASSESSMENT_CERTIFICATE = 'certificate';
    public const ASSESSMENT_PROCES_VERBAL = 'proces_verbal';
    public const ASSESSMENT_TEST = 'test';

    public const ASSESSMENT_METHODS = [
        self::ASSESSMENT_CERTIFICATE,
        self::ASSESSMENT_PROCES_VERBAL,
        self::ASSESSMENT_TEST,
    ];

    protected $table = 'training_courses';

    protected $casts = [
        'duration_hours' => 'decimal:2',
        'training_course_type_id' => 'integer',
        'training_course_assessment_method_id' => 'integer',
        'periodicity_months' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(FactoryDepartment::class, 'factory_department_training_course', 'training_course_id', 'factory_department_id')
            ->withTimestamps();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TrainingCourseType::class, 'training_course_type_id');
    }

    public function assessmentMethod(): BelongsTo
    {
        return $this->belongsTo(TrainingCourseAssessmentMethod::class, 'training_course_assessment_method_id');
    }

    public function label(): string
    {
        $name = $this->getAttribute('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return (string) $this->getAttribute('code');
    }

    /**
     * @return array<int, int>
     */
    public function departmentIds(): array
    {
        return $this->departments
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function departmentLabels(): array
    {
        return $this->departments
            ->map(static fn (FactoryDepartment $department): string => $department->label())
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function typeId(): ?int
    {
        $typeId = $this->getAttribute('training_course_type_id');

        return $typeId !== null ? (int) $typeId : null;
    }

    public function typeLabel(): ?string
    {
        return $this->type?->label();
    }

    public function assessmentMethodId(): ?int
    {
        $methodId = $this->getAttribute('training_course_assessment_method_id');

        return $methodId !== null ? (int) $methodId : null;
    }

    public function assessmentMethodLabel(): ?string
    {
        return $this->assessmentMethod?->label();
    }
}
