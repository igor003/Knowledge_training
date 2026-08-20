<?php

namespace App\Domain\User\Repository;

use App\Domain\Role\Model\Role;
use App\Domain\User\Model\User;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class UserRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    public function count(): int
    {
        $this->eloquent->boot();

        return User::query()->count();
    }

    public function findById(int $id): ?User
    {
        $this->eloquent->boot();

        return User::query()->find($id);
    }

    public function findActiveByEmail(string $email): ?User
    {
        $this->eloquent->boot();

        return User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->where('active', true)
            ->first();
    }

    public function findByEmail(string $email): ?User
    {
        $this->eloquent->boot();

        return User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->first();
    }

    public function findByName(string $name): ?User
    {
        $this->eloquent->boot();

        return User::query()
            ->where('name', trim($name))
            ->first();
    }

    public function findActiveByName(string $name): ?User
    {
        $this->eloquent->boot();

        return User::query()
            ->where('name', trim($name))
            ->where('active', true)
            ->first();
    }

    public function findByNameExcept(string $name, int $exceptId): ?User
    {
        $this->eloquent->boot();

        return User::query()
            ->where('name', trim($name))
            ->where('id', '!=', $exceptId)
            ->first();
    }

    public function findByEmailExcept(string $email, int $exceptId): ?User
    {
        $this->eloquent->boot();

        return User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->where('id', '!=', $exceptId)
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->with('roleModel')
            ->get();
    }

    public function create(string $name, string $email, string $password, Role $role): User
    {
        $this->eloquent->boot();

        return User::query()->create([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $role->id,
            'role' => $role->code,
            'active' => true,
        ]);
    }

    public function update(User $user, string $name, string $email, Role $role, bool $active): User
    {
        $user->name = trim($name);
        $user->email = mb_strtolower(trim($email));
        $user->role_id = $role->id;
        $user->role = $role->code;
        $user->active = $active;
        $user->save();

        return $user;
    }

    public function setActive(User $user, bool $active): void
    {
        $user->active = $active;
        $user->save();
    }

    public function setPassword(User $user, string $password): void
    {
        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user->save();
    }

    public function touchLastLogin(User $user): void
    {
        $user->last_login_at = Carbon::now();
        $user->save();
    }
}
