<?php

namespace App\Domain\Role\Repository;

use App\Domain\Role\Model\Role;
use App\Domain\User\Model\User;
use App\Service\Eloquent\EloquentManager;
use App\Service\Text\CodeGenerator;
use Illuminate\Database\Eloquent\Collection;

final class RoleRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return Role::query()
            ->withCount('users')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Role>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return Role::query()
            ->where('active', true)
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?Role
    {
        $this->eloquent->boot();

        return Role::query()->find($id);
    }

    public function findActiveById(int $id): ?Role
    {
        $this->eloquent->boot();

        return Role::query()
            ->where('id', $id)
            ->where('active', true)
            ->first();
    }

    public function findByCode(string $code): ?Role
    {
        $this->eloquent->boot();

        return Role::query()
            ->where('code', trim($code))
            ->first();
    }

    public function findByCodeExcept(string $code, int $exceptId): ?Role
    {
        $this->eloquent->boot();

        return Role::query()
            ->where('code', trim($code))
            ->where('id', '!=', $exceptId)
            ->first();
    }

    public function nextCodeFromName(string $name, string $fallback = 'role'): string
    {
        $this->eloquent->boot();

        $generator = new CodeGenerator();
        $base = $generator->baseFromName($name, $fallback, 60);

        for ($index = 1; $index < 1000; $index++) {
            $candidate = $generator->candidate($base, 60, $index);
            $exists = Role::query()
                ->where('code', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return $generator->candidate($base, 60, random_int(1000, 9999));
    }

    public function create(
        string $code,
        string $name,
        bool $isAdmin,
    ): Role {
        $this->eloquent->boot();
        $normalizedName = trim($name);

        return Role::query()->create([
            'code' => $this->normalizeCode($code),
            'name' => $normalizedName,
            'name_ru' => $normalizedName,
            'name_ro' => $normalizedName,
            'name_it' => $normalizedName,
            'name_fr' => $normalizedName,
            'is_admin' => $isAdmin,
            'active' => true,
        ]);
    }

    public function update(
        Role $role,
        string $code,
        string $name,
        bool $isAdmin,
        bool $active,
    ): Role {
        $normalizedName = trim($name);

        $role->code = $this->normalizeCode($code);
        $role->name = $normalizedName;
        $role->name_ru = $normalizedName;
        $role->name_ro = $normalizedName;
        $role->name_it = $normalizedName;
        $role->name_fr = $normalizedName;
        $role->is_admin = $isAdmin;
        $role->active = $active;
        $role->save();

        User::query()
            ->where('role_id', $role->id)
            ->update(['role' => $role->code]);

        return $role;
    }

    public function setActive(Role $role, bool $active): void
    {
        $role->active = $active;
        $role->save();
    }

    public function findDefaultAdmin(): ?Role
    {
        $this->eloquent->boot();

        return Role::query()
            ->where('active', true)
            ->where('is_admin', true)
            ->orderBy('id')
            ->first();
    }

    public function findDefaultManager(): ?Role
    {
        $this->eloquent->boot();

        return Role::query()
            ->where('active', true)
            ->where('is_admin', false)
            ->orderBy('id')
            ->first();
    }

    public function roleAllowsUserManagement(?int $roleId, string $legacyRole): bool
    {
        $role = $roleId !== null ? $this->findById($roleId) : $this->findByCode($legacyRole);

        return ($role?->active ?? false) && ($role->is_admin ?? false);
    }

    public function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
