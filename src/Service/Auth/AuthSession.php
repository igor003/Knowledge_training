<?php

namespace App\Service\Auth;

use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuthSession
{
    public const ATTEMPT_SUCCESS = 'success';
    public const ATTEMPT_INVALID = 'invalid';
    public const ATTEMPT_INACTIVE = 'inactive';

    private const SESSION_USER_ID = 'admin_user_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UserRepository $users,
        private readonly PermissionRepository $permissions,
    ) {
    }

    public function attempt(string $name, string $password): string
    {
        $user = $this->users->findByName($name);

        if ($user === null || !password_verify($password, $user->password_hash)) {
            return self::ATTEMPT_INVALID;
        }

        if (!$user->active || !$user->hasActiveRole()) {
            return self::ATTEMPT_INACTIVE;
        }

        $session = $this->requestStack->getSession();
        $session->migrate(true);
        $session->set(self::SESSION_USER_ID, $user->id);

        $this->users->touchLastLogin($user);

        return self::ATTEMPT_SUCCESS;
    }

    public function login(User $user): void
    {
        $session = $this->requestStack->getSession();
        $session->migrate(true);
        $session->set(self::SESSION_USER_ID, $user->id);
    }

    public function logout(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_USER_ID);
        $session->migrate(true);
    }

    public function user(): ?User
    {
        $id = $this->requestStack->getSession()->get(self::SESSION_USER_ID);

        if ($id === null) {
            return null;
        }

        $user = $this->users->findById((int) $id);

        if ($user === null || !$user->active || !$user->hasActiveRole()) {
            return null;
        }

        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function can(string $entityCode, string $action): bool
    {
        $user = $this->user();

        return $this->permissions->can($user?->role_id, $entityCode, $action);
    }
}
