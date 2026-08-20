<?php

namespace App\Controller\Admin;

use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Access\Model\RolePermission;
use App\Domain\FactoryDepartment\Repository\FactoryDepartmentRepository;
use App\Domain\TrainingCourse\Repository\TrainingCourseRepository;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FactoryDepartmentController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'factory_departments';
    private const ROUTE_PREFIX = 'admin_factory_departments';

    private const COLORS = [
        ['value' => '#C8E8D2', 'label' => 'catalog.color_green'],
        ['value' => '#F3D0AE', 'label' => 'catalog.color_orange'],
        ['value' => '#C5DCF2', 'label' => 'catalog.color_blue'],
        ['value' => '#DFD0EE', 'label' => 'catalog.color_lilac'],
        ['value' => '#F1DEAA', 'label' => 'catalog.color_yellow'],
        ['value' => '#F0C8D0', 'label' => 'catalog.color_rose'],
        ['value' => '#D9DEE5', 'label' => 'catalog.color_gray'],
    ];

    public function __construct(private readonly TrainingCourseRepository $trainingCourses)
    {
    }

    #[Route('/admin/factory-departments', name: 'admin_factory_departments_index', methods: ['GET'])]
    public function index(AuthSession $auth, FactoryDepartmentRepository $departments): Response
    {
        return $this->renderIndex($auth, $departments, PermissionRepository::ENTITY_FACTORY_DEPARTMENTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-departments/create', name: 'admin_factory_departments_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, FactoryDepartmentRepository $departments, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $departments, $audit, PermissionRepository::ENTITY_FACTORY_DEPARTMENTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-departments/reorder', name: 'admin_factory_departments_reorder', methods: ['POST'])]
    public function reorder(Request $request, AuthSession $auth, FactoryDepartmentRepository $departments, AuditLogger $audit): JsonResponse
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_FACTORY_DEPARTMENTS, RolePermission::ACTION_UPDATE);

        $orderedIds = $request->request->all('department_ids');
        if ($orderedIds === []) {
            $payload = json_decode($request->getContent(), true);
            $orderedIds = is_array($payload) ? ($payload['department_ids'] ?? []) : [];
        }

        if (!is_array($orderedIds) || $orderedIds === []) {
            return new JsonResponse(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $before = ['departments' => $departments->orderSnapshot()];
        $departments->reorder($orderedIds);
        $after = ['departments' => $departments->orderSnapshot()];

        $audit->log(
            $request,
            $auth,
            RolePermission::ACTION_UPDATE,
            PermissionRepository::ENTITY_FACTORY_DEPARTMENTS,
            null,
            'factory_departments_order',
            $before,
            $after,
            ['reorder' => true],
        );

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/admin/factory-departments/{id}/update', name: 'admin_factory_departments_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, FactoryDepartmentRepository $departments, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $departments, $audit, PermissionRepository::ENTITY_FACTORY_DEPARTMENTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-departments/{id}/toggle-status', name: 'admin_factory_departments_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, FactoryDepartmentRepository $departments, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $departments, $audit, PermissionRepository::ENTITY_FACTORY_DEPARTMENTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-departments/{id}/delete', name: 'admin_factory_departments_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, FactoryDepartmentRepository $departments, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_FACTORY_DEPARTMENTS, RolePermission::ACTION_DELETE);

        $department = $departments->findById($id);
        if ($department === null) {
            return $this->redirectToRoute(self::ROUTE_PREFIX . '_index');
        }

        $before = $this->catalogSnapshot($department);
        $audit->log(
            $request,
            $auth,
            RolePermission::ACTION_DELETE,
            PermissionRepository::ENTITY_FACTORY_DEPARTMENTS,
            $department->id,
            $department->label(),
            $before,
            null,
            ['physical_delete' => true],
        );

        $departments->physicallyDelete($department);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute(self::ROUTE_PREFIX . '_index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogTemplateVariables(): array
    {
        return [
            'show_department_count' => true,
            'show_color' => true,
            'show_column_filters' => true,
            'show_reorder_controls' => true,
            'row_reorder_route' => self::ROUTE_PREFIX . '_reorder',
            'color_options' => self::COLORS,
            'show_training_courses' => true,
            'can_physical_delete' => true,
            'physical_delete_confirmation' => 'factory_departments.confirm_physical_delete',
            'training_courses' => $this->trainingCourses->listActive(),
        ];
    }

    protected function extraCatalogForm(Request $request): array
    {
        return [
            'color' => $this->normalizeColor((string) $request->request->get('color', $request->request->get('color_preset', self::COLORS[0]['value']))),
            'training_course_ids' => array_values(array_unique(array_map('intval', (array) $request->request->all('training_course_ids')))),
        ];
    }

    protected function extraCatalogAttributes(array $form): array
    {
        return ['color' => $this->normalizeColor((string) ($form['color'] ?? self::COLORS[0]['value']))];
    }

    protected function extraCatalogSnapshot(SimpleCatalogModel $record): array
    {
        $record->loadMissing('trainingCourses');

        return [
            'color' => (string) ($record->getAttribute('color') ?: self::COLORS[0]['value']),
            'sort_order' => (int) $record->getAttribute('sort_order'),
            'training_course_ids' => $record->trainingCourseIds(),
            'training_courses' => $record->trainingCourseLabels(),
        ];
    }

    protected function afterCreateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
        if ($repository instanceof FactoryDepartmentRepository && $record instanceof \App\Domain\FactoryDepartment\Model\FactoryDepartment) {
            $record->trainingCourses()->sync($this->activeTrainingCourseIds($form['training_course_ids'] ?? []));
            $record->load('trainingCourses');
        }
    }

    protected function afterUpdateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
        $this->afterCreateRecord($record, $form, $repository);
    }

    /** @param array<int, mixed> $ids */
    private function activeTrainingCourseIds(array $ids): array
    {
        $courses = $this->trainingCourses->listActive();
        $active = [];
        foreach ($courses as $course) {
            $active[(int) $course->id] = true;
        }

        $result = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($active[$id])) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }

    private function normalizeColor(string $color): string
    {
        foreach (self::COLORS as $option) {
            if (strcasecmp($color, $option['value']) === 0) {
                return $option['value'];
            }
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return self::COLORS[0]['value'];
        }

        $red = hexdec(substr($color, 1, 2));
        $green = hexdec(substr($color, 3, 2));
        $blue = hexdec(substr($color, 5, 2));

        // Blend custom colors with white so user-selected shades stay soft.
        $red = (int) round($red * 0.45 + 255 * 0.55);
        $green = (int) round($green * 0.45 + 255 * 0.55);
        $blue = (int) round($blue * 0.45 + 255 * 0.55);

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }
}
