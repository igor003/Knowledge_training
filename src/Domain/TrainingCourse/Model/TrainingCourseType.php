<?php

namespace App\Domain\TrainingCourse\Model;

use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TrainingCourseType extends BaseModel
{
    protected $table = 'training_course_types';

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(TrainingCourse::class, 'training_course_type_id');
    }

    public function label(): string
    {
        $name = $this->getAttribute('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return (string) $this->getAttribute('code');
    }
}
