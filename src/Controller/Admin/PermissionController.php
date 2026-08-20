<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Role\Repository\RoleRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PermissionController extends AbstractController
{
    #[Route('/admin/permissions', name: 'admin_permissions_index', methods: ['GET'])]
    public function index(AuthSession $auth, RoleRepository $roles, PermissionRepository $permissions): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_PERMISSIONS, RolePermission::ACTION_READ);

        return $this->renderPermissionsIndex($auth, $roles, $permissions);
    }

    #[Route('/admin/permissions/update', name: 'admin_permissions_update', methods: ['POST'])]
    public function update(Request $request, AuthSession $auth, RoleRepository $roles, PermissionRepository $permissions, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_PERMISSIONS, RolePermission::ACTION_UPDATE);

        $rolesList = $roles->list();
        $entities = $permissions->listEntities();
        $before = $this->permissionMatrixSnapshot($rolesList, $entities, $permissions->matrix());
        $submitted = $request->request->all('permissions');
        $matrix = [];

        foreach ($rolesList as $role) {
            foreach ($entities as $entity) {
                $rawActions = $submitted[$role->id][$entity->id] ?? [];
                $matrix[$role->id][$entity->id] = [
                    RolePermission::ACTION_READ => isset($rawActions[RolePermission::ACTION_READ]),
                    RolePermission::ACTION_CREATE => isset($rawActions[RolePermission::ACTION_CREATE]),
                    RolePermission::ACTION_UPDATE => isset($rawActions[RolePermission::ACTION_UPDATE]),
                    RolePermission::ACTION_DEACTIVATE => isset($rawActions[RolePermission::ACTION_DEACTIVATE]),
                    RolePermission::ACTION_DELETE => PermissionRepository::entitySupportsAction($entity, RolePermission::ACTION_DELETE)
                        && isset($rawActions[RolePermission::ACTION_DELETE]),
                ];
            }
        }

        $error = $this->selfPermissionError($auth, $entities, $matrix);
        if ($error !== null) {
            return $this->renderPermissionsIndex($auth, $roles, $permissions, [
                'action_error' => $error,
                'matrix' => $this->temporaryMatrix($rolesList, $entities, $matrix),
            ]);
        }

        $after = $this->permissionMatrixSnapshot($rolesList, $entities, $this->temporaryMatrix($rolesList, $entities, $matrix));
        $permissions->updateMatrix($matrix);
        $audit->log(
            $request,
            $auth,
            RolePermission::ACTION_UPDATE,
            PermissionRepository::ENTITY_PERMISSIONS,
            null,
            'permission_matrix',
            $before,
            $after,
            [
                'roles_count' => count($rolesList),
                'entities_count' => count($entities),
                'changed_items' => $this->changedMatrixItems($before, $after),
            ],
        );
        $this->addFlash('success', 'success.permissions_updated');

        return $this->redirectToRoute('admin_permissions_index');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderPermissionsIndex(
        AuthSession $auth,
        RoleRepository $roles,
        PermissionRepository $permissions,
        array $overrides = [],
    ): Response {
        return $this->render('admin/permissions/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'roles' => $roles->list(),
            'entities' => $permissions->listEntities(),
            'actions' => PermissionRepository::ACTIONS,
            'action_availability' => $this->actionAvailability($permissions->listEntities()),
            'matrix' => $permissions->matrix(),
            'can_update' => $auth->can(PermissionRepository::ENTITY_PERMISSIONS, RolePermission::ACTION_UPDATE),
            'action_error' => null,
        ], $overrides));
    }

    /**
     * @param array<int, array<int, array<string, bool>>> $matrix
     */
    private function selfPermissionError(AuthSession $auth, iterable $entities, array $matrix): ?string
    {
        $currentRoleId = $auth->user()?->role_id;
        if ($currentRoleId === null) {
            return 'error.self_permission_update_restricted';
        }

        foreach ($entities as $entity) {
            if ($entity->code !== PermissionRepository::ENTITY_PERMISSIONS) {
                continue;
            }

            $selfPermission = $matrix[$currentRoleId][$entity->id] ?? [];
            if (
                !($selfPermission[RolePermission::ACTION_READ] ?? false)
                || !($selfPermission[RolePermission::ACTION_UPDATE] ?? false)
            ) {
                return 'error.self_permission_update_restricted';
            }
        }

        return null;
    }

    /**
     * @param array<int, array<int, array<string, bool>>> $matrix
     * @return array<int, array<int, RolePermission>>
     */
    private function temporaryMatrix(iterable $roles, iterable $entities, array $matrix): array
    {
        $result = [];

        foreach ($roles as $role) {
            foreach ($entities as $entity) {
                $actions = $matrix[$role->id][$entity->id] ?? [];
                $permission = new RolePermission();
                $permission->role_id = $role->id;
                $permission->permission_entity_id = $entity->id;
                $permission->can_read = $actions[RolePermission::ACTION_READ] ?? false;
                $permission->can_create = $actions[RolePermission::ACTION_CREATE] ?? false;
                $permission->can_update = $actions[RolePermission::ACTION_UPDATE] ?? false;
                $permission->can_deactivate = $actions[RolePermission::ACTION_DEACTIVATE] ?? false;
                $permission->can_delete = PermissionRepository::entitySupportsAction($entity, RolePermission::ACTION_DELETE)
                    && ($actions[RolePermission::ACTION_DELETE] ?? false);

                $result[$role->id][$entity->id] = $permission;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, array<string, bool>>>
     */
    private function permissionMatrixSnapshot(iterable $roles, iterable $entities, array $matrix): array
    {
        $result = [];

        foreach ($roles as $role) {
            foreach ($entities as $entity) {
                $permission = $matrix[$role->id][$entity->id] ?? null;

                $result[$role->code][$entity->code] = [
                    RolePermission::ACTION_READ => $permission instanceof RolePermission && $permission->can_read,
                    RolePermission::ACTION_CREATE => $permission instanceof RolePermission && $permission->can_create,
                    RolePermission::ACTION_UPDATE => $permission instanceof RolePermission && $permission->can_update,
                    RolePermission::ACTION_DEACTIVATE => $permission instanceof RolePermission && $permission->can_deactivate,
                    RolePermission::ACTION_DELETE => PermissionRepository::entitySupportsAction($entity, RolePermission::ACTION_DELETE)
                        && $permission instanceof RolePermission
                        && $permission->can_delete,
                ];
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, array<string, bool>>> $before
     * @param array<string, array<string, array<string, bool>>> $after
     */
    private function changedMatrixItems(array $before, array $after): int
    {
        $changed = 0;

        foreach ($after as $roleCode => $entities) {
            foreach ($entities as $entityCode => $actions) {
                if (($before[$roleCode][$entityCode] ?? []) !== $actions) {
                    ++$changed;
                }
            }
        }

        return $changed;
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    /**
     * @return array<int, array<string, bool>>
     */
    private function actionAvailability(iterable $entities): array
    {
        $availability = [];

        foreach ($entities as $entity) {
            foreach (PermissionRepository::ACTIONS as $action) {
                $availability[$entity->id][$action] = PermissionRepository::entitySupportsAction($entity, $action);
            }
        }

        return $availability;
    }
}
