<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Role\Model\Role;
use App\Domain\Role\Repository\RoleRepository;
use App\Domain\User\Repository\UserRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 4;

    #[Route('/admin/users', name: 'admin_users_index', methods: ['GET'])]
    public function index(AuthSession $auth, UserRepository $users, RoleRepository $roles): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_USERS, RolePermission::ACTION_READ);

        return $this->renderUsersIndex($auth, $users, $roles);
    }

    #[Route('/admin/users/create', name: 'admin_users_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, UserRepository $users, RoleRepository $roles, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_USERS, RolePermission::ACTION_CREATE);

        $error = null;
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $roleId = $request->request->getInt('role_id');
        $role = $roleId > 0 ? $roles->findActiveById($roleId) : null;
        $defaultRole = $this->defaultRole($roles);

        if ($role === null) {
            $error = 'error.invalid_role';
        } elseif ($name === '' || $email === '' || strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $error = 'error.required_user_fields';
        } elseif ($users->findByName($name) !== null) {
            $error = 'error.name_exists';
        } elseif ($users->findByEmail($email) !== null) {
            $error = 'error.email_exists';
        } else {
            $createdUser = $users->create($name, $email, $password, $role);
            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_CREATE,
                PermissionRepository::ENTITY_USERS,
                $createdUser->id,
                $createdUser->name,
                null,
                $this->userSnapshot($createdUser, $role),
                ['password_changed' => true],
            );
            $this->addFlash('success', 'success.created');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/index.html.twig', [
            'current_user' => $auth->user(),
            'users' => $users->list(),
            'roles' => $roles->listActive(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DELETE),
            'create_error' => $error,
            'open_create_modal' => true,
            'create_form' => [
                'name' => $name,
                'email' => $email,
                'role_id' => $role?->id ?? $defaultRole?->id,
            ],
            'edit_error' => null,
            'open_edit_user_id' => null,
            'edit_form' => [],
            'action_error' => null,
        ]);
    }

    #[Route('/admin/users/{id}/update', name: 'admin_users_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, UserRepository $users, RoleRepository $roles, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_USERS, RolePermission::ACTION_UPDATE);

        $user = $users->findById($id);
        if ($user === null) {
            throw $this->createNotFoundException('User not found.');
        }

        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $roleId = $request->request->getInt('role_id');
        $role = $roleId > 0 ? $roles->findActiveById($roleId) : null;
        $active = $request->request->getBoolean('active');
        $error = null;
        $defaultRole = $this->defaultRole($roles);

        if ($role === null) {
            $error = 'error.invalid_role';
        } elseif ($name === '' || $email === '') {
            $error = 'error.required_update_fields';
        } elseif ($users->findByNameExcept($name, $id) !== null) {
            $error = 'error.name_exists';
        } elseif ($users->findByEmailExcept($email, $id) !== null) {
            $error = 'error.email_exists';
        } elseif ($auth->user()?->id === $id && (!$active || $role->id !== $user->role_id)) {
            $error = 'error.self_update_restricted';
        } else {
            $before = $this->userSnapshot($user);
            $users->update($user, $name, $email, $role, $active);
            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_UPDATE,
                PermissionRepository::ENTITY_USERS,
                $user->id,
                $user->name,
                $before,
                $this->userSnapshot($user, $role),
            );
            $this->addFlash('success', 'success.updated');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/index.html.twig', [
            'current_user' => $auth->user(),
            'users' => $users->list(),
            'roles' => $roles->listActive(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DELETE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => [
                'name' => '',
                'email' => '',
                'role_id' => $defaultRole?->id,
            ],
            'edit_error' => $error,
            'open_edit_user_id' => $id,
            'edit_form' => [
                'name' => $name,
                'email' => $email,
                'role_id' => $role?->id ?? $defaultRole?->id,
                'active' => $active,
            ],
            'action_error' => null,
        ]);
    }

    #[Route('/admin/users/{id}/toggle-status', name: 'admin_users_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, UserRepository $users, RoleRepository $roles, AuditLogger $audit): Response
    {
        if ($auth->user()?->id === $id) {
            return $this->render('admin/users/index.html.twig', [
                'current_user' => $auth->user(),
                'users' => $users->list(),
                'roles' => $roles->listActive(),
                'can_create' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_CREATE),
                'can_update' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_UPDATE),
                'can_deactivate' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DEACTIVATE),
                'can_delete' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DELETE),
                'create_error' => null,
                'open_create_modal' => false,
                'create_form' => [
                    'name' => '',
                    'email' => '',
                    'role_id' => $this->defaultRole($roles)?->id,
                ],
                'edit_error' => null,
                'open_edit_user_id' => null,
                'edit_form' => [],
                'action_error' => 'error.self_disable',
            ]);
        }

        $user = $users->findById($id);
        if ($user !== null) {
            $permissionAction = $user->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE;
            $auditAction = $user->active ? 'deactivate' : 'activate';
            $this->denyPermission($auth, PermissionRepository::ENTITY_USERS, $permissionAction);
            $before = $this->userSnapshot($user);
            $users->setActive($user, !$user->active);
            $audit->log(
                $request,
                $auth,
                $auditAction,
                PermissionRepository::ENTITY_USERS,
                $user->id,
                $user->name,
                $before,
                $this->userSnapshot($user),
                ['status_toggle' => true],
            );
            $this->addFlash('success', $user->active ? 'success.activated' : 'success.deactivated');
        }

        return $this->redirectToRoute('admin_users_index');
    }

    private function renderUsersIndex(AuthSession $auth, UserRepository $users, RoleRepository $roles): Response
    {
        $defaultRole = $this->defaultRole($roles);

        return $this->render('admin/users/index.html.twig', [
            'current_user' => $auth->user(),
            'users' => $users->list(),
            'roles' => $roles->listActive(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can(PermissionRepository::ENTITY_USERS, RolePermission::ACTION_DELETE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => [
                'name' => '',
                'email' => '',
                'role_id' => $defaultRole?->id,
            ],
            'edit_error' => null,
            'open_edit_user_id' => null,
            'edit_form' => [],
            'action_error' => null,
        ]);
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    private function defaultRole(RoleRepository $roles): ?Role
    {
        return $roles->findDefaultManager() ?? $roles->findDefaultAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    private function userSnapshot(\App\Domain\User\Model\User $user, ?Role $role = null): array
    {
        $roleModel = $role ?? $user->roleModel;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role_code' => $roleModel?->code ?? $user->role,
            'role_name' => $roleModel?->label('ru') ?? $user->role,
            'active' => (bool) $user->active,
        ];
    }
}
