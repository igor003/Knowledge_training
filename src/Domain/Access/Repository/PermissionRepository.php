<?php

namespace App\Domain\Access\Repository;

use App\Domain\Access\Model\PermissionEntity;
use App\Domain\Access\Model\RolePermission;
use App\Domain\Role\Model\Role;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class PermissionRepository
{
    public const ENTITY_FACTORY_BRANCHES = 'factory_branches';
    public const ENTITY_FACTORY_DEPARTMENTS = 'factory_departments';
    public const ENTITY_FACTORY_SECTIONS = 'factory_sections';
    public const ENTITY_FACTORY_FUNCTIONS = 'factory_functions';
    public const ENTITY_FACTORY_FUNCTION_TYPES = 'factory_function_types';
    public const ENTITY_WORK_SHIFTS = 'work_shifts';
    public const ENTITY_EMPLOYEES = 'employees';
    public const ENTITY_EMPLOYEE_HISTORY = 'employee_history';
    public const ENTITY_EMPLOYEE_PERIODS = 'employee_periods';
    public const ENTITY_EMPLOYEE_STATUSES = 'employee_statuses';
    public const ENTITY_COMPETENCIES = 'competencies';
    public const ENTITY_TRAINING_COURSES = 'training_courses';
    public const ENTITY_TRAINING_COURSE_TYPES = 'training_course_types';
    public const ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS = 'training_course_assessment_methods';
    public const ENTITY_TRAINING_COURSE_PLANS = 'training_course_plans';
    public const ENTITY_USERS = 'users';
    public const ENTITY_ROLES = 'roles';
    public const ENTITY_PERMISSIONS = 'permissions';
    public const ENTITY_AUDIT_LOGS = 'audit_logs';

    public const ACTIONS = [
        RolePermission::ACTION_READ,
        RolePermission::ACTION_CREATE,
        RolePermission::ACTION_UPDATE,
        RolePermission::ACTION_DEACTIVATE,
        RolePermission::ACTION_DELETE,
    ];

    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, PermissionEntity>
     */
    public function listEntities(): Collection
    {
        $this->eloquent->boot();

        return PermissionEntity::query()
            ->where('active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return array<int, array<int, RolePermission>>
     */
    public function matrix(): array
    {
        $this->eloquent->boot();
        $matrix = [];

        RolePermission::query()
            ->with(['role', 'entity'])
            ->get()
            ->each(function (RolePermission $permission) use (&$matrix): void {
                $matrix[$permission->role_id][$permission->permission_entity_id] = $permission;
            });

        return $matrix;
    }

    /**
     * @param array<int, array<int, array<string, bool>>> $matrix
     */
    public function updateMatrix(array $matrix): void
    {
        $this->eloquent->boot();

        foreach ($matrix as $roleId => $entities) {
            foreach ($entities as $entityId => $actions) {
                $entity = PermissionEntity::query()->find($entityId);

                RolePermission::query()->updateOrCreate(
                    [
                        'role_id' => $roleId,
                        'permission_entity_id' => $entityId,
                    ],
                    [
                        'can_read' => $actions[RolePermission::ACTION_READ] ?? false,
                        'can_create' => $actions[RolePermission::ACTION_CREATE] ?? false,
                        'can_update' => $actions[RolePermission::ACTION_UPDATE] ?? false,
                        'can_deactivate' => $actions[RolePermission::ACTION_DEACTIVATE] ?? false,
                        'can_delete' => $entity instanceof PermissionEntity
                            && self::entitySupportsAction($entity, RolePermission::ACTION_DELETE)
                            && ($actions[RolePermission::ACTION_DELETE] ?? false),
                    ],
                );
            }
        }
    }

    public function can(?int $roleId, string $entityCode, string $action): bool
    {
        $this->eloquent->boot();

        if ($roleId === null || !in_array($action, self::ACTIONS, true)) {
            return false;
        }

        $role = Role::query()->find($roleId);
        if ($role === null || !$role->active) {
            return false;
        }

        $entity = PermissionEntity::query()
            ->where('code', $entityCode)
            ->where('active', true)
            ->first();

        if (!$entity instanceof PermissionEntity || !self::entitySupportsAction($entity, $action)) {
            return false;
        }

        $permission = RolePermission::query()
            ->where('role_id', $roleId)
            ->where('permission_entity_id', $entity->id)
            ->first();

        if ($permission instanceof RolePermission) {
            return $permission->allows($action);
        }

        return $role->is_admin;
    }

    public static function entitySupportsAction(PermissionEntity $entity, string $action): bool
    {
        if ($action !== RolePermission::ACTION_DELETE) {
            return true;
        }

        return (bool) $entity->supports_delete;
    }
}
