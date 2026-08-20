<?php

namespace App\Controller\Admin;

use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Access\Model\RolePermission;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shift\Repository\WorkShiftRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WorkShiftController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'work_shifts';
    private const ROUTE_PREFIX = 'admin_work_shifts';

    #[Route('/admin/work-shifts', name: 'admin_work_shifts_index', methods: ['GET'])]
    public function index(AuthSession $auth, WorkShiftRepository $shifts): Response
    {
        return $this->renderIndex($auth, $shifts, PermissionRepository::ENTITY_WORK_SHIFTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/work-shifts/create', name: 'admin_work_shifts_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, WorkShiftRepository $shifts, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $shifts, $audit, PermissionRepository::ENTITY_WORK_SHIFTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/work-shifts/{id}/update', name: 'admin_work_shifts_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, WorkShiftRepository $shifts, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $shifts, $audit, PermissionRepository::ENTITY_WORK_SHIFTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/work-shifts/{id}/toggle-status', name: 'admin_work_shifts_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, WorkShiftRepository $shifts, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $shifts, $audit, PermissionRepository::ENTITY_WORK_SHIFTS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/work-shifts/{id}/delete', name: 'admin_work_shifts_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, WorkShiftRepository $shifts, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_WORK_SHIFTS, RolePermission::ACTION_DELETE);

        $shift = $shifts->findById($id);
        if ($shift === null) {
            return $this->redirectToRoute(self::ROUTE_PREFIX.'_index');
        }

        $before = $this->catalogSnapshot($shift);
        $audit->log($request, $auth, RolePermission::ACTION_DELETE, PermissionRepository::ENTITY_WORK_SHIFTS, $shift->id, $shift->label(), $before, null, ['physical_delete' => true]);
        $shifts->physicallyDelete($shift);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute(self::ROUTE_PREFIX.'_index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogTemplateVariables(): array
    {
        return [
            'show_work_time' => true,
            'can_physical_delete' => true,
            'physical_delete_confirmation' => 'work_shifts.confirm_physical_delete',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogForm(Request $request): array
    {
        return [
            'work_time' => $this->normalizeWorkTime((string) $request->request->get('work_time', '')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function validateExtraCatalogForm(array $form): ?string
    {
        $workTime = (string) ($form['work_time'] ?? '');

        if ($workTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d - (?:[01]\d|2[0-3]):[0-5]\d$/', $workTime)) {
            return 'error.invalid_work_time';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function extraCatalogAttributes(array $form): array
    {
        return [
            'work_time' => (($workTime = trim((string) ($form['work_time'] ?? ''))) !== '') ? $workTime : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogSnapshot(SimpleCatalogModel $record): array
    {
        return [
            'work_time' => $record->getAttribute('work_time'),
        ];
    }

    private function normalizeWorkTime(string $workTime): string
    {
        $workTime = trim($workTime);

        return (string) preg_replace('/\s*-\s*/', ' - ', $workTime);
    }
}
