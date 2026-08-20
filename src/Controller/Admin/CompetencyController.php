<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Competency\Model\Competency;
use App\Domain\Competency\Repository\CompetencyRepository;
use App\Domain\Department\Repository\DepartmentRepository;
use App\Domain\FactoryDepartment\Repository\FactoryDepartmentRepository;
use App\Domain\Position\Repository\PositionRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CompetencyController extends AbstractController
{
    private const ENTITY = PermissionRepository::ENTITY_COMPETENCIES;

    #[Route('/admin/competencies', name: 'admin_competencies_index', methods: ['GET'])]
    public function index(AuthSession $auth, CompetencyRepository $competencies, DepartmentRepository $sections, PositionRepository $functions, FactoryDepartmentRepository $factoryDepartments): Response
    {
        $this->deny($auth, RolePermission::ACTION_READ);

        return $this->renderIndex($auth, $competencies, $sections, $functions, $factoryDepartments);
    }

    #[Route('/admin/competencies/matrix', name: 'admin_competencies_matrix', methods: ['GET'])]
    public function matrix(Request $request, AuthSession $auth, CompetencyRepository $competencies, DepartmentRepository $sections, FactoryDepartmentRepository $factoryDepartments): Response
    {
        $this->deny($auth, RolePermission::ACTION_READ);

        $allSections = $sections->list()->filter(static fn ($section): bool => (bool) $section->active)->values();
        $selectedFactoryDepartmentId = max(0, (int) $request->query->get('factory_department_id', 0));
        $selectedSectionId = max(0, (int) $request->query->get('factory_section_id', 0));
        $selectedFunctionId = max(0, (int) $request->query->get('factory_function_id', 0));
        $selectedCompetencyName = trim((string) $request->query->get('competency_name', ''));
        $filterSections = $allSections
            ->filter(static fn ($section): bool => $selectedFactoryDepartmentId === 0 || (int) $section->factory_department_id === $selectedFactoryDepartmentId)
            ->values();

        if ($selectedSectionId > 0 && !$filterSections->contains('id', $selectedSectionId)) {
            $selectedSectionId = 0;
        }

        $baseColumns = $this->matrixColumns($filterSections, $selectedSectionId);
        $filterFunctions = $this->matrixFunctionOptions($baseColumns);
        if ($selectedFunctionId > 0 && !array_key_exists($selectedFunctionId, $filterFunctions)) {
            $selectedFunctionId = 0;
        }

        $columns = $selectedFunctionId > 0
            ? array_values(array_filter($baseColumns, static fn (array $column): bool => (int) $column['function_id'] === $selectedFunctionId))
            : $baseColumns;
        $competencyList = $this->filterCompetenciesByName($competencies->list(), $selectedCompetencyName);

        return $this->render('admin/competencies/matrix.html.twig', [
            'current_user' => $auth->user(),
            'competencies' => $competencyList,
            'factory_departments' => $factoryDepartments->listActive(),
            'filter_sections' => $filterSections,
            'filter_functions' => $filterFunctions,
            'selected_factory_department_id' => $selectedFactoryDepartmentId,
            'selected_section_id' => $selectedSectionId,
            'selected_function_id' => $selectedFunctionId,
            'selected_competency_name' => $selectedCompetencyName,
            'matrix_columns' => $columns,
            'matrix_column_groups' => $selectedFactoryDepartmentId === 0 ? $this->matrixColumnGroups($columns) : [],
            'matrix_section_groups' => $selectedSectionId === 0 ? $this->matrixSectionGroups($columns) : [],
            'matrix_states' => $this->matrixStates($competencyList),
        ]);
    }

    #[Route('/admin/competencies/create', name: 'admin_competencies_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, CompetencyRepository $competencies, DepartmentRepository $sections, PositionRepository $functions, FactoryDepartmentRepository $factoryDepartments, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_CREATE);
        $form = $this->form($request);
        $error = $this->validate($form, $competencies, $sections);

        if ($error !== null) {
            return $this->renderIndex($auth, $competencies, $sections, $functions, $factoryDepartments, [
                'create_error' => $error,
                'open_create_modal' => true,
                'create_form' => $form,
            ]);
        }

        $competency = $competencies->create($this->attributes($form));
        $competencies->syncFunctionPairs($competency, $form['function_pair_objects']);
        $audit->log($request, $auth, RolePermission::ACTION_CREATE, self::ENTITY, $competency->id, $competency->label(), null, $this->snapshot($competency));
        $this->addFlash('success', 'success.created');

        return $this->redirectToRoute('admin_competencies_index');
    }

    #[Route('/admin/competencies/{id}/update', name: 'admin_competencies_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, CompetencyRepository $competencies, DepartmentRepository $sections, PositionRepository $functions, FactoryDepartmentRepository $factoryDepartments, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_UPDATE);
        $competency = $competencies->findById($id);
        if ($competency === null) {
            throw $this->createNotFoundException('Competency not found.');
        }

        $form = $this->form($request, $competency);
        $error = $this->validate($form, $competencies, $sections, $competency->id);
        if ($error !== null) {
            return $this->renderIndex($auth, $competencies, $sections, $functions, $factoryDepartments, [
                'edit_error' => $error,
                'open_edit_competency_id' => $id,
                'edit_form' => $form,
            ]);
        }

        $before = $this->snapshot($competency);
        $competencies->update($competency, $this->attributes($form));
        $competencies->syncFunctionPairs($competency, $form['function_pair_objects']);
        $audit->log($request, $auth, RolePermission::ACTION_UPDATE, self::ENTITY, $competency->id, $competency->label(), $before, $this->snapshot($competency));
        $this->addFlash('success', 'success.updated');

        return $this->redirectToRoute('admin_competencies_index');
    }

    #[Route('/admin/competencies/{id}/toggle-status', name: 'admin_competencies_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, CompetencyRepository $competencies, AuditLogger $audit): Response
    {
        $competency = $competencies->findById($id);
        if ($competency === null) {
            return $this->redirectToRoute('admin_competencies_index');
        }

        $permissionAction = $competency->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE;
        $auditAction = $competency->active ? 'deactivate' : 'activate';
        $this->deny($auth, $permissionAction);
        $before = $this->snapshot($competency);
        $competencies->setActive($competency, !$competency->active);
        $audit->log($request, $auth, $auditAction, self::ENTITY, $competency->id, $competency->label(), $before, $this->snapshot($competency), ['status_toggle' => true]);
        $this->addFlash('success', $competency->active ? 'success.activated' : 'success.deactivated');

        return $this->redirectToRoute('admin_competencies_index');
    }

    #[Route('/admin/competencies/{id}/delete', name: 'admin_competencies_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, CompetencyRepository $competencies, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_DELETE);

        $competency = $competencies->findById($id);
        if ($competency === null) {
            return $this->redirectToRoute('admin_competencies_index');
        }

        $before = $this->snapshot($competency);
        $audit->log($request, $auth, RolePermission::ACTION_DELETE, self::ENTITY, $competency->id, $competency->label(), $before, null, ['physical_delete' => true]);
        $competencies->physicallyDelete($competency);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute('admin_competencies_index');
    }

    private function renderIndex(AuthSession $auth, CompetencyRepository $competencies, DepartmentRepository $sections, PositionRepository $functions, FactoryDepartmentRepository $factoryDepartments, array $overrides = []): Response
    {
        $allSections = $sections->list();

        return $this->render('admin/competencies/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'competencies' => $competencies->list(),
            'sections' => $allSections->filter(static fn ($section): bool => (bool) $section->active)->values(),
            'functions' => $functions->listActive(),
            'factory_departments' => $factoryDepartments->list(),
            'can_create' => $auth->can(self::ENTITY, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(self::ENTITY, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(self::ENTITY, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can(self::ENTITY, RolePermission::ACTION_DELETE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => $this->emptyForm(),
            'edit_error' => null,
            'open_edit_competency_id' => null,
            'edit_form' => [],
        ], $overrides));
    }

    private function form(Request $request, ?Competency $competency = null): array
    {
        $pairs = $this->requestPairs($request, $competency);

        return [
            'name' => trim((string) $request->request->get('name', $competency?->name ?? '')),
            'function_pairs' => array_map(static fn (array $pair): string => $pair['factory_section_id'].':'.$pair['factory_function_id'], $pairs),
            'function_pair_objects' => $pairs,
            'function_pair_critical' => array_reduce($pairs, static function (array $critical, array $pair): array {
                $critical[$pair['factory_section_id'].':'.$pair['factory_function_id']] = $pair['critical'];

                return $critical;
            }, []),
            'competency_type' => (string) $request->request->get('competency_type', $competency?->competency_type ?? Competency::TYPE_SKILL),
            'minimum_score' => (int) $request->request->get('minimum_score', $competency?->minimum_score ?? 0),
        ];
    }

    private function emptyForm(): array
    {
        return ['name' => '', 'function_pairs' => [], 'function_pair_objects' => [], 'function_pair_critical' => [], 'competency_type' => Competency::TYPE_SKILL, 'minimum_score' => 0];
    }

    private function validate(array $form, CompetencyRepository $competencies, DepartmentRepository $sections, ?int $exceptId = null): ?string
    {
        if ($form['name'] === '') {
            return 'error.required_competency_fields';
        }
        if (!in_array($form['competency_type'], [Competency::TYPE_SKILL, Competency::TYPE_KNOWLEDGE], true)) {
            return 'error.invalid_competency_type';
        }
        if ($form['minimum_score'] < 0 || $form['minimum_score'] > 100) {
            return 'error.invalid_competency_threshold';
        }
        if ($form['function_pair_objects'] === []) {
            return 'error.invalid_competency_pair';
        }
        $availableSections = $sections->list();
        foreach ($form['function_pair_objects'] as $pair) {
            $section = $availableSections->firstWhere('id', $pair['factory_section_id']);
            if ($section === null || !$section->positions->contains('id', $pair['factory_function_id'])) {
                return 'error.invalid_competency_pair';
            }
        }
        if ($competencies->existsByName($form['name'], $exceptId)) {
            return 'error.duplicate_competency';
        }

        return null;
    }

    private function attributes(array $form): array
    {
        $firstPair = $form['function_pair_objects'][0];

        return ['name' => $form['name'], 'factory_section_id' => $firstPair['factory_section_id'], 'factory_function_id' => $firstPair['factory_function_id'], 'competency_type' => $form['competency_type'], 'critical' => $firstPair['critical'], 'minimum_score' => $form['minimum_score']];
    }

    private function snapshot(Competency $competency): array
    {
        return ['id' => $competency->id, 'name' => $competency->name, 'function_pairs' => $competency->functionAssignments->map(static fn ($assignment): array => ['factory_section_id' => $assignment->factory_section_id, 'factory_function_id' => $assignment->factory_function_id, 'critical' => (bool) $assignment->critical])->all(), 'competency_type' => $competency->competency_type, 'minimum_score' => $competency->minimum_score, 'active' => (bool) $competency->active];
    }

    /** @return array<int, array{factory_section_id:int, factory_function_id:int, critical:bool}> */
    private function requestPairs(Request $request, ?Competency $competency = null): array
    {
        $rawPairs = $request->request->all('function_pairs');
        if ($rawPairs === [] && $competency !== null) {
            return $competency->functionAssignments
                ->map(static fn ($assignment): array => ['factory_section_id' => (int) $assignment->factory_section_id, 'factory_function_id' => (int) $assignment->factory_function_id, 'critical' => (bool) $assignment->critical])
                ->all();
        }

        $criticalPairs = $request->request->all('function_pair_critical');
        $pairs = [];
        foreach (is_array($rawPairs) ? $rawPairs : [] as $rawPair) {
            [$sectionId, $functionId] = array_pad(explode(':', (string) $rawPair, 2), 2, '0');
            $sectionId = (int) $sectionId;
            $functionId = (int) $functionId;
            if ($sectionId > 0 && $functionId > 0) {
                $key = $sectionId.':'.$functionId;
                $pairs[$key] = ['factory_section_id' => $sectionId, 'factory_function_id' => $functionId, 'critical' => !empty($criticalPairs[$key])];
            }
        }

        return array_values($pairs);
    }

    private function deny(AuthSession $auth, string $action): void
    {
        if (!$auth->can(self::ENTITY, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    /**
     * @return array<int, array{key:string, factory_department_id:int, factory_department_order:int, factory_department:string, department_color:string, section_id:int, section:string, function_id:int, function_order:int, function:string, group_start:bool, section_group_start:bool}>
     */
    private function matrixColumns(iterable $sections, int $selectedSectionId): array
    {
        $columns = [];
        foreach ($sections as $section) {
            if ($selectedSectionId > 0 && (int) $section->id !== $selectedSectionId) {
                continue;
            }

            foreach ($section->positions as $function) {
                if (!$function->active) {
                    continue;
                }

                $key = $section->id.':'.$function->id;
                $columns[$key] = [
                    'key' => $key,
                    'factory_department_id' => (int) $section->factory_department_id,
                    'factory_department_order' => (int) ($section->factoryDepartment?->getAttribute('sort_order') ?? 0),
                    'factory_department' => $section->factoryDepartmentLabel(),
                    'department_color' => $this->departmentColor($section),
                    'section_id' => (int) $section->id,
                    'section' => $section->label(),
                    'function_id' => (int) $function->id,
                    'function_order' => (int) ($function->pivot?->sort_order ?? 0),
                    'function' => $function->label(),
                    'group_start' => false,
                    'section_group_start' => false,
                ];
            }
        }

        $columns = array_values($columns);
        usort($columns, static fn (array $left, array $right): int => [
            $left['factory_department_order'],
            $left['factory_department'],
            $left['section'],
            $left['function_order'],
            $left['function'],
        ] <=> [
            $right['factory_department_order'],
            $right['factory_department'],
            $right['section'],
            $right['function_order'],
            $right['function'],
        ]);

        $previousDepartmentId = null;
        $previousSectionKey = null;
        foreach ($columns as $index => $column) {
            $departmentId = (int) $column['factory_department_id'];
            $sectionKey = $departmentId.':'.(int) $column['section_id'];
            $columns[$index]['group_start'] = $previousDepartmentId === null || $previousDepartmentId !== $departmentId;
            $columns[$index]['section_group_start'] = $previousSectionKey === null || $previousSectionKey !== $sectionKey;
            $previousDepartmentId = $departmentId;
            $previousSectionKey = $sectionKey;
        }

        return $columns;
    }

    /**
     * @param array<int, array{function_id:int, function:string}> $columns
     * @return array<int, string>
     */
    private function matrixFunctionOptions(array $columns): array
    {
        $functions = [];
        foreach ($columns as $column) {
            $functions[(int) $column['function_id']] = $column['function'];
        }

        asort($functions, SORT_NATURAL | SORT_FLAG_CASE);

        return $functions;
    }

    /**
     * @param array<int, array{factory_department_id:int, factory_department:string, department_color:string}> $columns
     * @return array<int, array{factory_department_id:int, factory_department:string, department_color:string, colspan:int}>
     */
    private function matrixColumnGroups(array $columns): array
    {
        $groups = [];
        foreach ($columns as $column) {
            $lastIndex = array_key_last($groups);
            if ($lastIndex !== null && $groups[$lastIndex]['factory_department_id'] === (int) $column['factory_department_id']) {
                ++$groups[$lastIndex]['colspan'];
                continue;
            }

            $groups[] = [
                'factory_department_id' => (int) $column['factory_department_id'],
                'factory_department' => $column['factory_department'],
                'department_color' => $column['department_color'],
                'colspan' => 1,
            ];
        }

        return $groups;
    }

    /**
     * @param array<int, array{factory_department_id:int, department_color:string, section_id:int, section:string}> $columns
     * @return array<int, array{factory_department_id:int, department_color:string, section_id:int, section:string, colspan:int, department_group_start:bool}>
     */
    private function matrixSectionGroups(array $columns): array
    {
        $groups = [];
        foreach ($columns as $column) {
            $lastIndex = array_key_last($groups);
            if (
                $lastIndex !== null
                && $groups[$lastIndex]['factory_department_id'] === (int) $column['factory_department_id']
                && $groups[$lastIndex]['section_id'] === (int) $column['section_id']
            ) {
                ++$groups[$lastIndex]['colspan'];
                continue;
            }

            $groups[] = [
                'factory_department_id' => (int) $column['factory_department_id'],
                'department_color' => $column['department_color'],
                'section_id' => (int) $column['section_id'],
                'section' => $column['section'],
                'colspan' => 1,
                'department_group_start' => $lastIndex === null || $groups[$lastIndex]['factory_department_id'] !== (int) $column['factory_department_id'],
            ];
        }

        return $groups;
    }

    private function departmentColor(object $section): string
    {
        $color = (string) ($section->factoryDepartment?->getAttribute('color') ?: '#C8E8D2');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#C8E8D2';
    }

    private function filterCompetenciesByName(iterable $competencies, string $name): iterable
    {
        if ($name === '') {
            return $competencies;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);

        $filtered = [];
        foreach ($competencies as $competency) {
            $label = $competency->label();
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label);
            if (str_contains($haystack, $needle)) {
                $filtered[] = $competency;
            }
        }

        return $filtered;
    }

    /**
     * @param iterable<int, Competency> $competencies
     * @return array<int, array<string, string>>
     */
    private function matrixStates(iterable $competencies): array
    {
        $states = [];
        foreach ($competencies as $competency) {
            foreach ($competency->functionAssignments as $assignment) {
                $states[(int) $competency->id][$assignment->factory_section_id.':'.$assignment->factory_function_id] = $assignment->critical ? 'critical' : 'noncritical';
            }
        }

        return $states;
    }
}
