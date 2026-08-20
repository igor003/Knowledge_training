<?php

namespace App\Command;

use App\Domain\Access\Model\PermissionEntity;
use App\Domain\Access\Model\RolePermission;
use App\Domain\Audit\Model\AuditLog;
use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\Role\Model\Role;
use App\Domain\TrainingCourse\Model\TrainingCourse;
use App\Domain\TrainingCourse\Model\TrainingCourseAssessmentMethod;
use App\Domain\User\Model\User;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'app:migrate', description: 'Create or update application database tables.')]
final class MigrateCommand
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable('roles')) {
            $schema->create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 120);
                $table->string('name_ru', 120);
                $table->string('name_ro', 120);
                $table->string('name_it', 120);
                $table->string('name_fr', 120);
                $table->boolean('is_admin')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            $io->success('Created table: roles');
        } else {
            $io->info('Table already exists: roles');

            if (!$schema->hasColumn('roles', 'name')) {
                $schema->table('roles', function (Blueprint $table): void {
                    $table->string('name', 120)->after('code')->default('');
                });

                $io->success('Added column: roles.name');
            }
        }

        $this->syncRoleNames();
        $this->seedDefaultRoles();

        if (!$schema->hasTable('permission_entities')) {
            $schema->create('permission_entities', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name_ru', 160);
                $table->string('name_ro', 160);
                $table->string('name_it', 160);
                $table->string('name_fr', 160);
                $table->boolean('active')->default(true);
                $table->boolean('supports_delete')->default(false);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });

            $io->success('Created table: permission_entities');
        } else {
            $io->info('Table already exists: permission_entities');
        }

        $this->ensurePermissionEntitySupportsDelete($io);
        $this->seedPermissionEntities();
        $this->syncSystemPermissionEntityFlags();

        if (!$schema->hasTable('users')) {
            $schema->create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160)->unique();
                $table->string('email', 190)->unique();
                $table->string('password_hash');
                $table->string('role', 40);
                $table->unsignedBigInteger('role_id')->nullable()->index();
                $table->boolean('active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });

            $io->success('Created table: users');
        } else {
            try {
                $schema->table('users', function (Blueprint $table): void {
                    $table->unique('name', 'users_name_unique');
                });

                $io->success('Added unique index: users.name');
            } catch (Throwable) {
                $io->info('Table already exists: users');
            }

            if (!$schema->hasColumn('users', 'role_id')) {
                $schema->table('users', function (Blueprint $table): void {
                    $table->unsignedBigInteger('role_id')->nullable()->after('role')->index();
                });

                $io->success('Added column: users.role_id');
            }
        }

        try {
            $schema->table('users', function (Blueprint $table): void {
                $table
                    ->foreign('role_id', 'users_role_id_foreign')
                    ->references('id')
                    ->on('roles');
            });

            $io->success('Added foreign key: users.role_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: users.role_id');
        }

        $this->syncUserRoles();
        $io->success('Synchronized user role links.');

        if (!$schema->hasTable('role_permissions')) {
            $schema->create('role_permissions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('role_id')->index();
                $table->unsignedBigInteger('permission_entity_id')->index();
                $table->boolean('can_read')->default(false);
                $table->boolean('can_create')->default(false);
                $table->boolean('can_update')->default(false);
                $table->boolean('can_deactivate')->default(false);
                $table->boolean('can_delete')->default(false);
                $table->timestamps();
                $table->unique(['role_id', 'permission_entity_id'], 'role_permissions_role_entity_unique');
            });

            $io->success('Created table: role_permissions');
        } else {
            $io->info('Table already exists: role_permissions');
        }

        $this->ensureRolePermissionDeactivate($io);

        try {
            $schema->table('role_permissions', function (Blueprint $table): void {
                $table
                    ->foreign('role_id', 'role_permissions_role_id_foreign')
                    ->references('id')
                    ->on('roles');
                $table
                    ->foreign('permission_entity_id', 'role_permissions_entity_id_foreign')
                    ->references('id')
                    ->on('permission_entities');
            });

            $io->success('Added foreign keys: role_permissions');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: role_permissions');
        }

        $this->syncRolePermissions();
        $this->clearUnsupportedDeletePermissions();
        $this->enableAdminFactoryDepartmentDeletePermission();
        $this->enableAdminTrainingCourseTypeDeletePermission();
        $this->enableAdminEmployeeStatusDeletePermission();
        $this->enableAdminWorkShiftDeletePermission();
        $this->enableAdminFactoryFunctionDeletePermission();
        $this->enableAdminCompetencyDeletePermission();
        $io->success('Synchronized role permission matrix.');

        $this->dropLegacyFactoryStructureTables($io);
        $this->ensureSimpleCatalogTable('factory_branches', $io);
        $this->ensureSimpleCatalogTable('factory_departments', $io);
        $this->ensureSimpleCatalogTable('factory_sections', $io);
        $this->ensureSimpleCatalogTable('factory_functions', $io);
        $this->ensureSimpleCatalogTable('factory_function_types', $io);
        $this->ensureSimpleCatalogTable('work_shifts', $io);
        $this->ensureSimpleCatalogTable('employee_statuses', $io);
        $this->ensureEmployeeTables($io);
        $this->ensureEmployeeBranchRelation($io);
        $this->ensureEmployeeStatusRelation($io);
        $this->ensureFactoryBranchAddress($io);
        $this->ensureFactoryBranchAlias($io);
        $this->ensureFactoryDepartmentColor($io);
        $this->ensureFactoryDepartmentSortOrder($io);
        $this->ensureFactorySectionHierarchy($io);
        $this->ensureFactorySectionProcessCode($io);
        $this->ensurePositionAlias($io);
        $this->ensureFunctionTypeRelation($io);
        $this->ensureWorkShiftTime($io);
        $this->ensureFactorySectionFunctionTable($io);
        $this->ensureCompetencyTables($io);
        $this->ensureTrainingCourseTables($io);
        $this->ensureTrainingCoursePlanTables($io);

        if (!$schema->hasTable('audit_logs')) {
            $schema->create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('actor_name', 160)->nullable();
                $table->string('actor_role_code', 60)->nullable();
                $table->string('action', 40)->index();
                $table->string('entity_code', 80)->index();
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->string('entity_label', 190)->nullable();
                $table->json('before_values')->nullable();
                $table->json('after_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('request_method', 12);
                $table->string('request_path', 255);
                $table->string('route_name', 120)->nullable();
                $table->string('referer', 512)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            $io->success('Created table: audit_logs');
        } else {
            $io->info('Table already exists: audit_logs');
        }

        return Command::SUCCESS;
    }

    private function seedDefaultRoles(): void
    {
        $this->eloquent->boot();

        $roles = [
            [
                'code' => Role::DEFAULT_ADMIN_CODE,
                'name' => 'Администратор',
                'name_ru' => 'Администратор',
                'name_ro' => 'Администратор',
                'name_it' => 'Администратор',
                'name_fr' => 'Администратор',
                'is_admin' => true,
                'active' => true,
            ],
            [
                'code' => Role::DEFAULT_MANAGER_CODE,
                'name' => 'Менеджер',
                'name_ru' => 'Менеджер',
                'name_ro' => 'Менеджер',
                'name_it' => 'Менеджер',
                'name_fr' => 'Менеджер',
                'is_admin' => false,
                'active' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role['code']],
                $role,
            );
        }
    }

    private function syncRoleNames(): void
    {
        $this->eloquent->boot();

        Role::query()
            ->where('name', '')
            ->each(function (Role $role): void {
                $role->name = (string) ($role->name_ru ?: $role->code);
                $role->save();
            });
    }

    private function syncUserRoles(): void
    {
        $this->eloquent->boot();

        $defaultRole = Role::query()
            ->where('code', Role::DEFAULT_MANAGER_CODE)
            ->first()
            ?? Role::query()->where('code', Role::DEFAULT_ADMIN_CODE)->first();

        if ($defaultRole === null) {
            return;
        }

        User::query()
            ->whereNull('role_id')
            ->each(function (User $user) use ($defaultRole): void {
                $role = Role::query()->where('code', (string) $user->role)->first() ?? $defaultRole;

                $user->role_id = $role->id;
                $user->role = $role->code;
                $user->save();
            });
    }

    private function seedPermissionEntities(): void
    {
        $this->eloquent->boot();

        $entities = [
            ['code' => 'factory_branches', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Филиалы', 'name_ro' => 'Filiale', 'name_it' => 'Filiali', 'name_fr' => 'Filiales'],
            ['code' => 'factory_departments', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Департаменты', 'name_ro' => 'Departamente', 'name_it' => 'Dipartimenti', 'name_fr' => 'Departements'],
            ['code' => 'factory_sections', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Отделы', 'name_ro' => 'Sectii', 'name_it' => 'Reparti', 'name_fr' => 'Services'],
            ['code' => 'factory_functions', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Функции', 'name_ro' => 'Functii', 'name_it' => 'Funzioni', 'name_fr' => 'Fonctions'],
            ['code' => 'factory_function_types', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Типы функций', 'name_ro' => 'Tipuri functii', 'name_it' => 'Tipi di funzione', 'name_fr' => 'Types de fonctions'],
            ['code' => 'departments', 'active' => false, 'supports_delete' => true, 'name_ru' => 'Старые отделы', 'name_ro' => 'Departamente vechi', 'name_it' => 'Reparti precedenti', 'name_fr' => 'Anciens departements'],
            ['code' => 'positions', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Старые должности', 'name_ro' => 'Posturi vechi', 'name_it' => 'Posizioni precedenti', 'name_fr' => 'Anciens postes'],
            ['code' => 'work_shifts', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Смены', 'name_ro' => 'Schimburi', 'name_it' => 'Turni', 'name_fr' => 'Equipes'],
            ['code' => 'training_courses', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Курсы обучения', 'name_ro' => 'Cursuri instruire', 'name_it' => 'Corsi formazione', 'name_fr' => 'Cours formation'],
            ['code' => 'training_course_types', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Типы курсов', 'name_ro' => 'Tipuri cursuri', 'name_it' => 'Tipi corso', 'name_fr' => 'Types de cours'],
            ['code' => 'training_course_assessment_methods', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Методы оценки курсов', 'name_ro' => 'Metode evaluare cursuri', 'name_it' => 'Metodi valutazione corsi', 'name_fr' => 'Methodes evaluation cours'],
            ['code' => 'training_course_plans', 'active' => true, 'supports_delete' => false, 'name_ru' => 'План курсов', 'name_ro' => 'Plan cursuri', 'name_it' => 'Piano corsi', 'name_fr' => 'Plan cours'],
            ['code' => 'functions', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Функции', 'name_ro' => 'Functii operationale', 'name_it' => 'Funzioni', 'name_fr' => 'Fonctions'],
            ['code' => 'employees', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Работники', 'name_ro' => 'Angajati', 'name_it' => 'Dipendenti', 'name_fr' => 'Employes'],
            ['code' => 'employee_history', 'active' => true, 'supports_delete' => false, 'name_ru' => 'История работников', 'name_ro' => 'Istoric angajati', 'name_it' => 'Storico dipendenti', 'name_fr' => 'Historique employes'],
            ['code' => 'employee_periods', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Отпуска и увольнения', 'name_ro' => 'Concedii si concedieri', 'name_it' => 'Ferie e licenziamenti', 'name_fr' => 'Conges et licenciements'],
            ['code' => 'employee_statuses', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Статусы работников', 'name_ro' => 'Statusuri angajati', 'name_it' => 'Stati dipendenti', 'name_fr' => 'Statuts employes'],
            ['code' => 'competencies', 'active' => true, 'supports_delete' => true, 'name_ru' => 'Компетенции', 'name_ro' => 'Competente', 'name_it' => 'Competenze', 'name_fr' => 'Competences'],
            ['code' => 'users', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Пользователи', 'name_ro' => 'Utilizatori', 'name_it' => 'Utenti', 'name_fr' => 'Utilisateurs'],
            ['code' => 'roles', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Роли', 'name_ro' => 'Roluri', 'name_it' => 'Ruoli', 'name_fr' => 'Roles'],
            ['code' => 'permissions', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Матрица доступов', 'name_ro' => 'Matrice acces', 'name_it' => 'Matrice accessi', 'name_fr' => 'Matrice des acces'],
            ['code' => 'audit_logs', 'active' => true, 'supports_delete' => false, 'name_ru' => 'Журнал действий', 'name_ro' => 'Jurnal actiuni', 'name_it' => 'Registro azioni', 'name_fr' => 'Journal actions'],
            ['code' => 'skill_matrix', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Skill matrix', 'name_ro' => 'Skill matrix', 'name_it' => 'Skill matrix', 'name_fr' => 'Skill matrix'],
            ['code' => 'topics', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Темы', 'name_ro' => 'Teme', 'name_it' => 'Argomenti', 'name_fr' => 'Sujets'],
            ['code' => 'questions', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Вопросы', 'name_ro' => 'Intrebari', 'name_it' => 'Domande', 'name_fr' => 'Questions'],
            ['code' => 'tests', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Тесты', 'name_ro' => 'Teste', 'name_it' => 'Test', 'name_fr' => 'Tests'],
            ['code' => 'test_attempts', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Попытки тестов', 'name_ro' => 'Incercari teste', 'name_it' => 'Tentativi test', 'name_fr' => 'Tentatives de test'],
            ['code' => 'reports', 'active' => false, 'supports_delete' => false, 'name_ru' => 'Отчеты', 'name_ro' => 'Rapoarte', 'name_it' => 'Report', 'name_fr' => 'Rapports'],
        ];

        foreach ($entities as $entity) {
            PermissionEntity::query()->updateOrCreate(
                ['code' => $entity['code']],
                $entity,
            );
        }
    }

    private function syncSystemPermissionEntityFlags(): void
    {
        $this->eloquent->boot();

        PermissionEntity::query()->update(['is_system' => false]);
        PermissionEntity::query()
            ->whereIn('code', [
                'roles',
                'permissions',
                'employee_statuses',
                'training_course_assessment_methods',
            ])
            ->update(['is_system' => true]);
    }

    private function syncRolePermissions(): void
    {
        $this->eloquent->boot();

        Role::query()->each(function (Role $role): void {
            PermissionEntity::query()->where('active', true)->each(function (PermissionEntity $entity) use ($role): void {
                $defaults = $role->is_admin
                    ? ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_deactivate' => true, 'can_delete' => (bool) $entity->supports_delete]
                    : ['can_read' => false, 'can_create' => false, 'can_update' => false, 'can_deactivate' => false, 'can_delete' => false];

                RolePermission::query()->firstOrCreate(
                    [
                        'role_id' => $role->id,
                        'permission_entity_id' => $entity->id,
                    ],
                    $defaults,
                );
            });
        });
    }

    private function ensurePermissionEntitySupportsDelete(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('permission_entities', 'supports_delete')) {
            $schema->table('permission_entities', function (Blueprint $table): void {
                $table->boolean('supports_delete')->default(false)->after('active');
            });

            $io->success('Added column: permission_entities.supports_delete');
        }

        if (!$schema->hasColumn('permission_entities', 'is_system')) {
            $schema->table('permission_entities', function (Blueprint $table): void {
                $table->boolean('is_system')->default(false)->after('supports_delete');
            });

            $io->success('Added column: permission_entities.is_system');
        }
    }

    private function clearUnsupportedDeletePermissions(): void
    {
        $this->eloquent->boot();

        $unsupportedEntityIds = PermissionEntity::query()
            ->where('supports_delete', false)
            ->pluck('id')
            ->all();

        if ($unsupportedEntityIds === []) {
            return;
        }

        RolePermission::query()
            ->whereIn('permission_entity_id', $unsupportedEntityIds)
            ->where('can_delete', true)
            ->update(['can_delete' => false]);
    }

    private function enableAdminFactoryDepartmentDeletePermission(): void
    {
        $this->eloquent->boot();

        $entity = PermissionEntity::query()->where('code', 'factory_departments')->first();
        if ($entity === null || !$entity->supports_delete) {
            return;
        }

        Role::query()->where('is_admin', true)->each(function (Role $role) use ($entity): void {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('permission_entity_id', $entity->id)
                ->update(['can_delete' => true]);
        });
    }

    private function enableAdminTrainingCourseTypeDeletePermission(): void
    {
        $this->eloquent->boot();

        $entity = PermissionEntity::query()->where('code', 'training_course_types')->first();
        if ($entity === null || !$entity->supports_delete) {
            return;
        }

        Role::query()->where('is_admin', true)->each(function (Role $role) use ($entity): void {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('permission_entity_id', $entity->id)
                ->update(['can_delete' => true]);
        });
    }

    private function enableAdminEmployeeStatusDeletePermission(): void
    {
        $this->eloquent->boot();

        $entity = PermissionEntity::query()->where('code', 'employee_statuses')->first();
        if ($entity === null || !$entity->supports_delete) {
            return;
        }

        Role::query()->where('is_admin', true)->each(function (Role $role) use ($entity): void {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('permission_entity_id', $entity->id)
                ->update(['can_delete' => true]);
        });
    }

    private function enableAdminWorkShiftDeletePermission(): void
    {
        $this->eloquent->boot();

        $entity = PermissionEntity::query()->where('code', 'work_shifts')->first();
        if ($entity === null || !$entity->supports_delete) {
            return;
        }

        Role::query()->where('is_admin', true)->each(function (Role $role) use ($entity): void {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('permission_entity_id', $entity->id)
                ->update(['can_delete' => true]);
        });
    }

    private function enableAdminFactoryFunctionDeletePermission(): void
    {
        $this->eloquent->boot();

        $entity = PermissionEntity::query()->where('code', 'factory_functions')->first();
        if ($entity === null || !$entity->supports_delete) {
            return;
        }

        Role::query()->where('is_admin', true)->each(function (Role $role) use ($entity): void {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('permission_entity_id', $entity->id)
                ->update(['can_delete' => true]);
        });
    }

    private function enableAdminCompetencyDeletePermission(): void
    {
        $this->eloquent->boot();

        $entity = PermissionEntity::query()->where('code', 'competencies')->first();
        if ($entity === null || !$entity->supports_delete) {
            return;
        }

        Role::query()->where('is_admin', true)->each(function (Role $role) use ($entity): void {
            RolePermission::query()
                ->where('role_id', $role->id)
                ->where('permission_entity_id', $entity->id)
                ->update(['can_delete' => true]);
        });
    }

    private function ensureRolePermissionDeactivate(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('role_permissions', 'can_deactivate')) {
            $schema->table('role_permissions', function (Blueprint $table): void {
                $table->boolean('can_deactivate')->default(false)->after('can_update');
            });

            $this->eloquent->boot();
            RolePermission::query()
                ->where('can_delete', true)
                ->update(['can_deactivate' => true]);

            $io->success('Added column: role_permissions.can_deactivate');
        }
    }

    private function ensureSimpleCatalogTable(string $tableName, SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable($tableName)) {
            $schema->create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                if ($tableName === 'factory_sections') {
                    $table->unsignedBigInteger('factory_department_id')->index();
                    $table->string('process_code', 2)->nullable();
                }
                if ($tableName === 'factory_branches') {
                    $table->string('address', 255);
                    $table->string('alias', 80)->nullable();
                }
                if ($tableName === 'factory_functions') {
                    $table->string('alias', 80)->nullable();
                }
                if ($tableName === 'work_shifts') {
                    $table->string('work_time', 20);
                }
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            $io->success('Created table: ' . $tableName);

            return;
        }

        $io->info('Table already exists: ' . $tableName);
    }

    private function ensureEmployeeTables(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable('employees')) {
            $schema->create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('face_id', 120)->unique();
                $table->string('name', 160);
                $table->unsignedBigInteger('factory_branch_id')->nullable()->index();
                $table->unsignedBigInteger('factory_department_id')->index();
                $table->unsignedBigInteger('factory_section_id')->index();
                $table->unsignedBigInteger('factory_function_id')->index();
                $table->string('status', 20)->default('active')->index();
                $table->date('last_hired_at')->nullable();
                $table->date('dismissed_at')->nullable();
                $table->unsignedBigInteger('work_shift_id')->nullable()->index();
                $table->boolean('formator')->default(false);
                $table->timestamps();
            });
            $io->success('Created table: employees');
        } else {
            $io->info('Table already exists: employees');
        }

        if (!$schema->hasTable('employee_assignment_history')) {
            $schema->create('employee_assignment_history', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('factory_department_id')->index();
                $table->unsignedBigInteger('factory_section_id')->index();
                $table->unsignedBigInteger('factory_function_id')->index();
                $table->unsignedBigInteger('work_shift_id')->nullable()->index();
                $table->date('date_from');
                $table->date('date_to')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
            });
            $io->success('Created table: employee_assignment_history');
        } else {
            $io->info('Table already exists: employee_assignment_history');
        }

        if (!$schema->hasTable('employee_periods')) {
            $schema->create('employee_periods', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('period_type', 20)->index();
                $table->date('date_from');
                $table->date('date_to')->nullable();
                $table->timestamps();
            });
            $io->success('Created table: employee_periods');
        } else {
            $io->info('Table already exists: employee_periods');
        }

        if (!$schema->hasColumn('employee_periods', 'note')) {
            $schema->table('employee_periods', function (Blueprint $table): void {
                $table->text('note')->nullable()->after('date_to');
            });
            $io->success('Added column: employee_periods.note');
        }

        if (!$schema->hasColumn('employee_periods', 'active')) {
            $schema->table('employee_periods', function (Blueprint $table): void {
                $table->boolean('active')->default(true)->index();
            });
            $io->success('Added column: employee_periods.active');
        }

        try {
            $schema->table('employees', function (Blueprint $table): void {
                $table->foreign('factory_department_id', 'employees_department_id_foreign')->references('id')->on('factory_departments');
                $table->foreign('factory_branch_id', 'employees_branch_id_foreign')->references('id')->on('factory_branches')->nullOnDelete();
                $table->foreign('factory_section_id', 'employees_section_id_foreign')->references('id')->on('factory_sections');
                $table->foreign('factory_function_id', 'employees_function_id_foreign')->references('id')->on('factory_functions');
                $table->foreign('work_shift_id', 'employees_shift_id_foreign')->references('id')->on('work_shifts');
            });
            $io->success('Added foreign keys: employees');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: employees');
        }

        try {
            $schema->table('employee_assignment_history', function (Blueprint $table): void {
                $table->foreign('employee_id', 'employee_assignment_history_employee_id_foreign')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('factory_department_id', 'employee_assignment_history_department_id_foreign')->references('id')->on('factory_departments');
                $table->foreign('factory_section_id', 'employee_assignment_history_section_id_foreign')->references('id')->on('factory_sections');
                $table->foreign('factory_function_id', 'employee_assignment_history_function_id_foreign')->references('id')->on('factory_functions');
                $table->foreign('work_shift_id', 'employee_assignment_history_shift_id_foreign')->references('id')->on('work_shifts');
            });
            $io->success('Added foreign keys: employee_assignment_history');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: employee_assignment_history');
        }

        try {
            $schema->table('employee_periods', function (Blueprint $table): void {
                $table->foreign('employee_id', 'employee_periods_employee_id_foreign')->references('id')->on('employees')->cascadeOnDelete();
            });
            $io->success('Added foreign key: employee_periods');
        } catch (Throwable) {
            $io->info('Foreign key already exists: employee_periods');
        }
    }

    private function ensureEmployeeBranchRelation(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('employees', 'factory_branch_id')) {
            $schema->table('employees', function (Blueprint $table): void {
                $table->unsignedBigInteger('factory_branch_id')->nullable()->after('name')->index();
            });
            $io->success('Added column: employees.factory_branch_id');
        }

        try {
            $schema->table('employees', function (Blueprint $table): void {
                $table
                    ->foreign('factory_branch_id', 'employees_branch_id_foreign')
                    ->references('id')
                    ->on('factory_branches')
                    ->nullOnDelete();
            });
            $io->success('Added foreign key: employees.factory_branch_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: employees.factory_branch_id');
        }
    }

    private function ensureEmployeeStatusRelation(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('employees', 'employee_status_id')) {
            $schema->table('employees', function (Blueprint $table): void {
                $table->unsignedBigInteger('employee_status_id')->nullable()->after('status')->index();
            });
            $io->success('Added column: employees.employee_status_id');
        }

        try {
            $schema->table('employees', function (Blueprint $table): void {
                $table
                    ->foreign('employee_status_id', 'employees_status_id_foreign')
                    ->references('id')
                    ->on('employee_statuses')
                    ->nullOnDelete();
            });
            $io->success('Added foreign key: employees.employee_status_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: employees.employee_status_id');
        }

        try {
            $schema->table('employee_statuses', function (Blueprint $table): void {
                $table->unique('name', 'employee_statuses_name_unique');
            });
            $io->success('Added unique index: employee_statuses.name');
        } catch (Throwable) {
            $io->info('Unique index already exists: employee_statuses.name');
        }
    }

    private function dropLegacyFactoryStructureTables(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();
        $legacyTables = [
            'training_course_monthly_plans',
            'department_training_course',
            'department_position',
            'departments',
            'positions',
        ];

        $hasLegacyTables = false;
        foreach ($legacyTables as $tableName) {
            if ($schema->hasTable($tableName)) {
                $hasLegacyTables = true;
                break;
            }
        }

        if (!$hasLegacyTables) {
            return;
        }

        $schema->disableForeignKeyConstraints();
        foreach ($legacyTables as $tableName) {
            if ($schema->hasTable($tableName)) {
                $schema->dropIfExists($tableName);
                $io->success('Dropped legacy table: ' . $tableName);
            }
        }
        $schema->enableForeignKeyConstraints();
    }

    private function ensureFactorySectionHierarchy(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_sections', 'factory_department_id')) {
            $this->clearFactorySectionStructureData($io);

            $schema->table('factory_sections', function (Blueprint $table): void {
                $table->unsignedBigInteger('factory_department_id')->after('id')->index();
            });

            $io->success('Added column: factory_sections.factory_department_id');
        }

        try {
            $schema->table('factory_sections', function (Blueprint $table): void {
                $table
                    ->foreign('factory_department_id', 'factory_sections_department_id_foreign')
                    ->references('id')
                    ->on('factory_departments');
            });

            $io->success('Added foreign key: factory_sections.factory_department_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: factory_sections.factory_department_id');
        }
    }

    private function clearFactorySectionStructureData(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();
        $this->eloquent->boot();
        $connection = (new PermissionEntity())->getConnection();

        foreach (['factory_section_function'] as $tableName) {
            if ($schema->hasTable($tableName)) {
                $connection->table($tableName)->delete();
            }
        }

        if ($schema->hasTable('factory_sections')) {
            $connection->table('factory_sections')->delete();
        }

        $io->success('Cleared factory section structure data.');
    }

    private function ensureFactorySectionProcessCode(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_sections', 'process_code')) {
            $schema->table('factory_sections', function (Blueprint $table): void {
                $table->string('process_code', 2)->nullable()->after('name');
            });

            $io->success('Added column: factory_sections.process_code');
        }
    }

    private function ensureFactoryBranchAddress(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_branches', 'address')) {
            $schema->table('factory_branches', function (Blueprint $table): void {
                $table->string('address', 255)->after('name')->default('');
            });

            $io->success('Added column: factory_branches.address');
        }
    }

    private function ensureFactoryBranchAlias(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_branches', 'alias')) {
            $schema->table('factory_branches', function (Blueprint $table): void {
                $table->string('alias', 80)->nullable()->after('name');
            });

            $io->success('Added column: factory_branches.alias');
        }
    }

    private function ensureFactoryDepartmentColor(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_departments', 'color')) {
            $schema->table('factory_departments', function (Blueprint $table): void {
                $table->string('color', 7)->default('#C8E8D2')->after('name');
            });

            $io->success('Added column: factory_departments.color');
        }

        $legacyColors = [
            '#DCEFE3' => '#C8E8D2',
            '#F6DFC8' => '#F3D0AE',
            '#DCEAF7' => '#C5DCF2',
            '#E9E0F4' => '#DFD0EE',
            '#F4E7C8' => '#F1DEAA',
            '#F2DCE1' => '#F0C8D0',
            '#E5E8EC' => '#D9DEE5',
        ];

        foreach ($legacyColors as $oldColor => $newColor) {
            FactoryDepartment::query()
                ->where('color', $oldColor)
                ->update(['color' => $newColor]);
        }
    }

    private function ensureFactoryDepartmentSortOrder(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_departments', 'sort_order')) {
            $schema->table('factory_departments', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0)->after('color')->index();
            });

            $io->success('Added column: factory_departments.sort_order');
        }

        $this->eloquent->boot();
        $departments = FactoryDepartment::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $order = 10;
        foreach ($departments as $department) {
            if ((int) $department->getAttribute('sort_order') === $order) {
                $order += 10;
                continue;
            }

            $department->setAttribute('sort_order', $order);
            $department->save();
            $order += 10;
        }
    }

    private function ensurePositionAlias(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_functions', 'alias')) {
            $schema->table('factory_functions', function (Blueprint $table): void {
                $table->string('alias', 80)->nullable()->after('name');
            });

            $io->success('Added column: factory_functions.alias');
        }
    }

    private function ensureFunctionTypeRelation(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('factory_functions', 'factory_function_type_id')) {
            $schema->table('factory_functions', function (Blueprint $table): void {
                $table->unsignedBigInteger('factory_function_type_id')->nullable()->after('alias')->index();
            });
            $io->success('Added column: factory_functions.factory_function_type_id');
        }

        try {
            $schema->table('factory_functions', function (Blueprint $table): void {
                $table
                    ->foreign('factory_function_type_id', 'factory_functions_type_id_foreign')
                    ->references('id')
                    ->on('factory_function_types')
                    ->nullOnDelete();
            });
            $io->success('Added foreign key: factory_functions.factory_function_type_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: factory_functions.factory_function_type_id');
        }
    }

    private function ensureWorkShiftTime(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasColumn('work_shifts', 'work_time')) {
            $schema->table('work_shifts', function (Blueprint $table): void {
                $table->string('work_time', 20)->nullable()->after('name');
            });

            $io->success('Added column: work_shifts.work_time');
        } else {
            try {
                $schema->table('work_shifts', function (Blueprint $table): void {
                    $table->string('work_time', 20)->nullable()->change();
                });
                $io->success('Made column nullable: work_shifts.work_time');
            } catch (Throwable) {
                $io->info('Column already nullable or could not be altered: work_shifts.work_time');
            }
        }
    }

    private function ensureFactorySectionFunctionTable(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable('factory_section_function')) {
            $schema->create('factory_section_function', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('factory_section_id')->index();
                $table->unsignedBigInteger('factory_function_id')->index();
                $table->boolean('critical')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
                $table->unique(['factory_section_id', 'factory_function_id'], 'factory_section_function_unique');
            });

            $io->success('Created table: factory_section_function');
        } else {
            $io->info('Table already exists: factory_section_function');
        }

        if (!$schema->hasColumn('factory_section_function', 'sort_order')) {
            $afterColumn = $schema->hasColumn('factory_section_function', 'critical') ? 'critical' : 'factory_function_id';
            $schema->table('factory_section_function', function (Blueprint $table) use ($afterColumn): void {
                $table->unsignedInteger('sort_order')->default(0)->after($afterColumn)->index();
            });

            $io->success('Added column: factory_section_function.sort_order');
        }

        $this->normalizeFactorySectionFunctionSortOrder();

        try {
            $schema->table('factory_section_function', function (Blueprint $table): void {
                $table
                    ->foreign('factory_section_id', 'factory_section_function_section_id_foreign')
                    ->references('id')
                    ->on('factory_sections')
                    ->cascadeOnDelete();
                $table
                    ->foreign('factory_function_id', 'factory_section_function_function_id_foreign')
                    ->references('id')
                    ->on('factory_functions')
                    ->cascadeOnDelete();
            });

            $io->success('Added foreign keys: factory_section_function');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: factory_section_function');
        }
    }

    private function normalizeFactorySectionFunctionSortOrder(): void
    {
        $this->eloquent->boot();
        $rows = FactoryDepartment::query()
            ->getConnection()
            ->table('factory_section_function')
            ->leftJoin('factory_functions', 'factory_section_function.factory_function_id', '=', 'factory_functions.id')
            ->select([
                'factory_section_function.id',
                'factory_section_function.factory_section_id',
                'factory_section_function.sort_order',
            ])
            ->orderBy('factory_section_function.factory_section_id')
            ->orderBy('factory_section_function.sort_order')
            ->orderBy('factory_functions.name')
            ->orderBy('factory_section_function.factory_function_id')
            ->get();

        $nextSortOrders = [];
        foreach ($rows as $row) {
            $sectionId = (int) $row->factory_section_id;
            $nextSortOrders[$sectionId] ??= 10;

            if ((int) $row->sort_order !== $nextSortOrders[$sectionId]) {
                FactoryDepartment::query()
                    ->getConnection()
                    ->table('factory_section_function')
                    ->where('id', (int) $row->id)
                    ->update(['sort_order' => $nextSortOrders[$sectionId]]);
            }

            $nextSortOrders[$sectionId] += 10;
        }
    }

    private function ensureCompetencyTables(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable('competencies')) {
            $schema->create('competencies', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 190);
                $table->unsignedBigInteger('factory_section_id')->index();
                $table->unsignedBigInteger('factory_function_id')->index();
                $table->string('competency_type', 20)->index();
                $table->boolean('critical')->default(false)->index();
                $table->unsignedTinyInteger('minimum_score')->default(0);
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
                $table->unique(['name', 'factory_section_id', 'factory_function_id'], 'competencies_name_pair_unique');
            });
            $io->success('Created table: competencies');
        } else {
            $io->info('Table already exists: competencies');
        }

        try {
            $schema->table('competencies', function (Blueprint $table): void {
                $table->foreign('factory_section_id', 'competencies_section_id_foreign')
                    ->references('id')->on('factory_sections')->cascadeOnDelete();
                $table->foreign('factory_function_id', 'competencies_function_id_foreign')
                    ->references('id')->on('factory_functions')->cascadeOnDelete();
            });
            $io->success('Added foreign keys: competencies');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: competencies');
        }

        if (!$schema->hasTable('competency_function')) {
            $schema->create('competency_function', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('competency_id')->index();
                $table->unsignedBigInteger('factory_section_id')->index();
                $table->unsignedBigInteger('factory_function_id')->index();
                $table->timestamps();
                $table->unique(['competency_id', 'factory_section_id', 'factory_function_id'], 'competency_function_unique');
            });
            $io->success('Created table: competency_function');
        } else {
            $io->info('Table already exists: competency_function');
        }

        if (!$schema->hasColumn('competency_function', 'critical')) {
            $schema->table('competency_function', function (Blueprint $table): void {
                $table->boolean('critical')->default(false)->index();
            });
            $io->success('Added column: competency_function.critical');
        }

        try {
            $schema->table('competency_function', function (Blueprint $table): void {
                $table->foreign('competency_id', 'competency_function_competency_id_foreign')->references('id')->on('competencies')->cascadeOnDelete();
                $table->foreign('factory_section_id', 'competency_function_section_id_foreign')->references('id')->on('factory_sections')->cascadeOnDelete();
                $table->foreign('factory_function_id', 'competency_function_function_id_foreign')->references('id')->on('factory_functions')->cascadeOnDelete();
            });
            $io->success('Added foreign keys: competency_function');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: competency_function');
        }

        $this->migrateLegacyCompetencyPairs($io);
    }

    private function migrateLegacyCompetencyPairs(SymfonyStyle $io): void
    {
        $this->eloquent->boot();

        \App\Domain\Competency\Model\Competency::query()
            ->whereDoesntHave('functionAssignments')
            ->whereNotNull('factory_section_id')
            ->whereNotNull('factory_function_id')
            ->each(function (\App\Domain\Competency\Model\Competency $competency): void {
                $competency->functionAssignments()->create([
                    'factory_section_id' => $competency->factory_section_id,
                    'factory_function_id' => $competency->factory_function_id,
                    'critical' => (bool) $competency->critical,
                ]);
            });
        $io->info('Synchronized legacy competency function pairs.');
    }

    private function ensureTrainingCourseTables(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable('training_course_types')) {
            $schema->create('training_course_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            $io->success('Created table: training_course_types');
        } else {
            $io->info('Table already exists: training_course_types');

            if (!$schema->hasColumn('training_course_types', 'description')) {
                $schema->table('training_course_types', function (Blueprint $table): void {
                    $table->text('description')->nullable()->after('name');
                });
            }
        }

        if (!$schema->hasTable('training_course_assessment_methods')) {
            $schema->create('training_course_assessment_methods', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            $io->success('Created table: training_course_assessment_methods');
        } else {
            $io->info('Table already exists: training_course_assessment_methods');

            if (!$schema->hasColumn('training_course_assessment_methods', 'description')) {
                $schema->table('training_course_assessment_methods', function (Blueprint $table): void {
                    $table->text('description')->nullable()->after('name');
                });
            }
        }

        if (!$schema->hasTable('training_courses')) {
            $schema->create('training_courses', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('training_course_type_id')->nullable()->index();
                $table->unsignedBigInteger('training_course_assessment_method_id')->nullable()->index();
                $table->decimal('duration_hours', 6, 2)->nullable();
                $table->unsignedSmallInteger('periodicity_months')->nullable();
                $table->string('assessment_method', 80);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            $io->success('Created table: training_courses');
        } else {
            $io->info('Table already exists: training_courses');

            if (!$schema->hasColumn('training_courses', 'description')) {
                $schema->table('training_courses', function (Blueprint $table): void {
                    $table->text('description')->nullable()->after('name');
                });
            }

            if (!$schema->hasColumn('training_courses', 'training_course_type_id')) {
                $schema->table('training_courses', function (Blueprint $table): void {
                    $table->unsignedBigInteger('training_course_type_id')->nullable()->after('description')->index();
                });

                $io->success('Added column: training_courses.training_course_type_id');
            }

            if (!$schema->hasColumn('training_courses', 'training_course_assessment_method_id')) {
                $schema->table('training_courses', function (Blueprint $table): void {
                    $table->unsignedBigInteger('training_course_assessment_method_id')->nullable()->after('training_course_type_id')->index();
                });

                $io->success('Added column: training_courses.training_course_assessment_method_id');
            }

            if (!$schema->hasColumn('training_courses', 'periodicity_months')) {
                $schema->table('training_courses', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('periodicity_months')->nullable()->after('description');
                });
            }

            if (!$schema->hasColumn('training_courses', 'duration_hours')) {
                $schema->table('training_courses', function (Blueprint $table): void {
                    $table->decimal('duration_hours', 6, 2)->nullable()->after('description');
                });
            }

            if (!$schema->hasColumn('training_courses', 'assessment_method')) {
                $schema->table('training_courses', function (Blueprint $table): void {
                    $table->string('assessment_method', 80)->after('periodicity_months')->default(TrainingCourse::ASSESSMENT_TEST);
                });
            }
        }

        try {
            $schema->table('training_courses', function (Blueprint $table): void {
                $table
                    ->foreign('training_course_type_id', 'training_courses_type_id_foreign')
                    ->references('id')
                    ->on('training_course_types')
                    ->nullOnDelete();
            });

            $io->success('Added foreign key: training_courses.training_course_type_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: training_courses.training_course_type_id');
        }

        try {
            $schema->table('training_courses', function (Blueprint $table): void {
                $table
                    ->foreign('training_course_assessment_method_id', 'training_courses_assessment_method_id_foreign')
                    ->references('id')
                    ->on('training_course_assessment_methods')
                    ->nullOnDelete();
            });

            $io->success('Added foreign key: training_courses.training_course_assessment_method_id');
        } catch (Throwable) {
            $io->info('Foreign key already exists: training_courses.training_course_assessment_method_id');
        }

        $this->migrateCourseAssessmentMethods($io);

        if (!$schema->hasTable('factory_department_training_course')) {
            $schema->create('factory_department_training_course', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('factory_department_id')->index();
                $table->unsignedBigInteger('training_course_id')->index();
                $table->timestamps();
                $table->unique(['factory_department_id', 'training_course_id'], 'factory_department_training_course_unique');
            });

            $io->success('Created table: factory_department_training_course');
        } else {
            $io->info('Table already exists: factory_department_training_course');
        }

        try {
            $schema->table('factory_department_training_course', function (Blueprint $table): void {
                $table
                    ->foreign('factory_department_id', 'factory_department_training_course_department_id_foreign')
                    ->references('id')
                    ->on('factory_departments')
                    ->cascadeOnDelete();
                $table
                    ->foreign('training_course_id', 'factory_department_training_course_course_id_foreign')
                    ->references('id')
                    ->on('training_courses')
                    ->cascadeOnDelete();
            });

            $io->success('Added foreign keys: factory_department_training_course');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: factory_department_training_course');
        }

        if (!$schema->hasTable('training_course_type_training_course')) {
            $schema->create('training_course_type_training_course', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('training_course_type_id');
                $table->unsignedBigInteger('training_course_id');
                $table->timestamps();
                $table->index('training_course_type_id', 'training_course_type_course_type_id_index');
                $table->index('training_course_id', 'training_course_type_course_course_id_index');
                $table->unique(['training_course_type_id', 'training_course_id'], 'training_course_type_course_unique');
            });

            $io->success('Created table: training_course_type_training_course');
        } else {
            $io->info('Table already exists: training_course_type_training_course');
        }

        try {
            $schema->table('training_course_type_training_course', function (Blueprint $table): void {
                $table->index('training_course_id', 'training_course_type_course_course_id_index');
            });

            $io->success('Added index: training_course_type_training_course.training_course_id');
        } catch (Throwable) {
            $io->info('Index already exists: training_course_type_training_course.training_course_id');
        }

        try {
            $schema->table('training_course_type_training_course', function (Blueprint $table): void {
                $table
                    ->foreign('training_course_type_id', 'training_course_type_course_type_id_foreign')
                    ->references('id')
                    ->on('training_course_types')
                    ->cascadeOnDelete();
                $table
                    ->foreign('training_course_id', 'training_course_type_course_course_id_foreign')
                    ->references('id')
                    ->on('training_courses')
                    ->cascadeOnDelete();
            });

            $io->success('Added foreign keys: training_course_type_training_course');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: training_course_type_training_course');
        }

        $this->migrateCourseTypesFromPivot($io);
    }

    private function migrateCourseTypesFromPivot(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();
        if (!$schema->hasColumn('training_courses', 'training_course_type_id') || !$schema->hasTable('training_course_type_training_course')) {
            return;
        }

        $updated = 0;
        TrainingCourse::query()
            ->whereNull('training_course_type_id')
            ->each(function (TrainingCourse $course) use (&$updated): void {
                $typeId = $course->getConnection()
                    ->table('training_course_type_training_course')
                    ->where('training_course_id', $course->id)
                    ->orderBy('id')
                    ->value('training_course_type_id');

                if ($typeId === null) {
                    return;
                }

                $course->training_course_type_id = (int) $typeId;
                $course->save();
                $updated++;
            });

        if ($updated > 0) {
            $io->success(sprintf('Migrated course type links from pivot: %d', $updated));
        }
    }

    private function migrateCourseAssessmentMethods(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();
        if (!$schema->hasColumn('training_courses', 'training_course_assessment_method_id')) {
            return;
        }

        $updated = 0;
        TrainingCourse::query()
            ->whereNull('training_course_assessment_method_id')
            ->each(function (TrainingCourse $course) use (&$updated): void {
                $code = trim((string) $course->assessment_method);
                if ($code === '') {
                    return;
                }

                $method = TrainingCourseAssessmentMethod::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $this->defaultAssessmentMethodName($code),
                        'description' => null,
                        'active' => true,
                    ],
                );

                $course->training_course_assessment_method_id = $method->id;
                $course->save();
                $updated++;
            });

        if ($updated > 0) {
            $io->success(sprintf('Migrated course assessment methods: %d', $updated));
        }
    }

    private function defaultAssessmentMethodName(string $code): string
    {
        return match ($code) {
            TrainingCourse::ASSESSMENT_CERTIFICATE => 'Сертификат',
            TrainingCourse::ASSESSMENT_PROCES_VERBAL => 'Proces verbal',
            TrainingCourse::ASSESSMENT_TEST => 'Тест',
            default => str_replace('_', ' ', $code),
        };
    }

    private function ensureTrainingCoursePlanTables(SymfonyStyle $io): void
    {
        $schema = $this->eloquent->schema();

        if (!$schema->hasTable('factory_department_training_course_monthly_plans')) {
            $schema->create('factory_department_training_course_monthly_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('factory_department_id')->index('fdcp_department_idx');
                $table->unsignedBigInteger('training_course_id')->index('fdcp_course_idx');
                $table->unsignedSmallInteger('year')->index('fdcp_year_idx');
                $table->unsignedTinyInteger('month')->index('fdcp_month_idx');
                $table->unsignedInteger('planned_count')->default(0);
                $table->timestamps();
                $table->unique(['factory_department_id', 'training_course_id', 'year', 'month'], 'factory_department_training_course_plan_unique');
            });

            $io->success('Created table: factory_department_training_course_monthly_plans');
        } else {
            $io->info('Table already exists: factory_department_training_course_monthly_plans');
        }

        try {
            $schema->table('factory_department_training_course_monthly_plans', function (Blueprint $table): void {
                $table
                    ->foreign('factory_department_id', 'factory_department_training_course_plans_department_id_foreign')
                    ->references('id')
                    ->on('factory_departments')
                    ->cascadeOnDelete();
                $table
                    ->foreign('training_course_id', 'factory_department_training_course_plans_course_id_foreign')
                    ->references('id')
                    ->on('training_courses')
                    ->cascadeOnDelete();
            });

            $io->success('Added foreign keys: factory_department_training_course_monthly_plans');
        } catch (Throwable) {
            $io->info('Foreign keys already exist: factory_department_training_course_monthly_plans');
        }

        if ($schema->hasColumn('factory_department_training_course_monthly_plans', 'completed_count')) {
            $schema->table('factory_department_training_course_monthly_plans', function (Blueprint $table): void {
                $table->dropColumn('completed_count');
            });

            $io->success('Removed column: factory_department_training_course_monthly_plans.completed_count');
        }
    }

}
