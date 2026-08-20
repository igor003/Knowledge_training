<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Role\Model\Role;
use App\Domain\Role\Repository\RoleRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RoleController extends AbstractController
{
    #[Route('/admin/roles', name: 'admin_roles_index', methods: ['GET'])]
    public function index(AuthSession $auth, RoleRepository $roles): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_READ);

        return $this->renderRolesIndex($auth, $roles);
    }

    #[Route('/admin/roles/create', name: 'admin_roles_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, RoleRepository $roles, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_CREATE);

        $form = $this->roleForm($request);
        $error = $this->validateRoleForm($form);

        if ($error === null) {
            $form['code'] = $roles->nextCodeFromName($form['name']);
            $createdRole = $roles->create(
                $form['code'],
                $form['name'],
                $form['is_admin'],
            );
            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_CREATE,
                PermissionRepository::ENTITY_ROLES,
                $createdRole->id,
                $createdRole->label('ru'),
                null,
                $this->roleSnapshot($createdRole),
            );
            $this->addFlash('success', 'success.created');

            return $this->redirectToRoute('admin_roles_index');
        }

        return $this->renderRolesIndex($auth, $roles, [
            'create_error' => $error,
            'open_create_modal' => true,
            'create_form' => $form,
        ]);
    }

    #[Route('/admin/roles/{id}/update', name: 'admin_roles_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, RoleRepository $roles, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_UPDATE);

        $role = $roles->findById($id);
        if ($role === null) {
            throw $this->createNotFoundException('Role not found.');
        }

        $form = $this->roleForm($request);
        $form['code'] = $role->code;
        $form['active'] = $request->request->getBoolean('active');
        $error = $this->validateRoleForm($form);
        $currentUser = $auth->user();

        if (
            $error === null
            && $currentUser?->role_id === $role->id
            && (!$form['active'] || ($role->is_admin && !$form['is_admin']))
        ) {
            $error = 'error.self_role_update_restricted';
        }

        if ($error === null) {
            $before = $this->roleSnapshot($role);
            $roles->update(
                $role,
                $form['code'],
                $form['name'],
                $form['is_admin'],
                $form['active'],
            );
            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_UPDATE,
                PermissionRepository::ENTITY_ROLES,
                $role->id,
                $role->label('ru'),
                $before,
                $this->roleSnapshot($role),
            );
            $this->addFlash('success', 'success.updated');

            return $this->redirectToRoute('admin_roles_index');
        }

        return $this->renderRolesIndex($auth, $roles, [
            'edit_error' => $error,
            'open_edit_role_id' => $id,
            'edit_form' => $form,
        ]);
    }

    #[Route('/admin/roles/{id}/toggle-status', name: 'admin_roles_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, RoleRepository $roles, AuditLogger $audit): Response
    {
        $role = $roles->findById($id);
        if ($role === null) {
            return $this->redirectToRoute('admin_roles_index');
        }

        $this->denyPermission(
            $auth,
            PermissionRepository::ENTITY_ROLES,
            $role->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE,
        );

        if ($auth->user()?->role_id === $role->id) {
            return $this->renderRolesIndex($auth, $roles, [
                'action_error' => 'error.self_role_disable',
            ]);
        }

        $auditAction = $role->active ? 'deactivate' : 'activate';
        $before = $this->roleSnapshot($role);
        $roles->setActive($role, !$role->active);
        $audit->log(
            $request,
            $auth,
            $auditAction,
            PermissionRepository::ENTITY_ROLES,
            $role->id,
            $role->label('ru'),
            $before,
            $this->roleSnapshot($role),
            ['status_toggle' => true],
        );
        $this->addFlash('success', $role->active ? 'success.activated' : 'success.deactivated');

        return $this->redirectToRoute('admin_roles_index');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderRolesIndex(AuthSession $auth, RoleRepository $roles, array $overrides = []): Response
    {
        return $this->render('admin/roles/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'roles' => $roles->list(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can(PermissionRepository::ENTITY_ROLES, RolePermission::ACTION_DELETE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => [
                'name' => '',
                'is_admin' => false,
            ],
            'edit_error' => null,
            'open_edit_role_id' => null,
            'edit_form' => [],
            'action_error' => null,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function roleForm(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('name', '')),
            'is_admin' => $request->request->getBoolean('is_admin'),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function validateRoleForm(array $form): ?string
    {
        if ($form['name'] === '') {
            return 'error.required_role_fields';
        }

        return null;
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function roleSnapshot(Role $role): array
    {
        return [
            'id' => $role->id,
            'code' => $role->code,
            'name' => $role->label('ru'),
            'is_admin' => (bool) $role->is_admin,
            'active' => (bool) $role->active,
        ];
    }
}
