<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class SimpleCatalogController extends AbstractController
{
    protected function renderIndex(
        AuthSession $auth,
        SimpleCatalogRepository $repository,
        string $entityCode,
        string $translationPrefix,
        string $routePrefix,
        array $overrides = [],
    ): Response {
        return $this->render('admin/simple_catalog/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'records' => $repository->list(),
            'entity_code' => $entityCode,
            'translation_prefix' => $translationPrefix,
            'route_prefix' => $routePrefix,
            'can_create' => $auth->can($entityCode, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can($entityCode, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can($entityCode, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can($entityCode, RolePermission::ACTION_DELETE),
            'can_physical_delete' => false,
            'physical_delete_confirmation' => 'catalog.confirm_physical_delete',
            'catalog_name_label' => 'catalog.name',
            'show_process_code' => false,
            'show_address' => false,
            'show_factory_department' => false,
            'show_color' => false,
            'show_column_filters' => true,
            'show_relation_department_filter' => false,
            'show_alias' => false,
            'show_work_time' => false,
            'show_departments' => false,
            'show_positions' => false,
            'show_training_courses' => false,
            'show_competencies' => false,
            'show_reorder_controls' => false,
            'show_department_count' => false,
            'show_function_type' => false,
            'show_function_count' => false,
            'departments' => [],
            'training_courses' => [],
            'competencies' => [],
            'factory_departments' => [],
            'color_options' => [],
            'function_types' => [],
            'positions' => [],
            'row_reorder_route' => null,
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => [
                'name' => '',
                'process_code' => '',
                'address' => '',
                'factory_department_id' => '',
                'color' => '',
                'alias' => '',
                'work_time' => '',
                'department_ids' => [],
                'position_ids' => [],
                'training_course_ids' => [],
                'competency_assignments' => [],
            ],
            'edit_error' => null,
            'open_edit_record_id' => null,
            'edit_form' => [],
            'action_error' => null,
        ], $this->catalogTemplateVariables(), $overrides));
    }

    protected function createRecord(
        Request $request,
        AuthSession $auth,
        SimpleCatalogRepository $repository,
        AuditLogger $audit,
        string $entityCode,
        string $translationPrefix,
        string $routePrefix,
    ): Response {
        $this->denyPermission($auth, $entityCode, RolePermission::ACTION_CREATE);

        $form = $this->catalogForm($request);
        $error = $this->validateCatalogForm($form);
        $error ??= $this->validateExtraCatalogForm($form);

        if ($error === null) {
            $form['code'] = $repository->nextCodeFromName($form['name'], $entityCode);
            $record = $repository->create($form['code'], $form['name'], $this->extraCatalogAttributes($form));
            $this->afterCreateRecord($record, $form, $repository);
            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_CREATE,
                $entityCode,
                $record->id,
                $record->label(),
                null,
                $this->catalogSnapshot($record),
            );
            $this->addFlash('success', 'success.created');

            return $this->redirectToRoute($routePrefix . '_index');
        }

        return $this->renderIndex($auth, $repository, $entityCode, $translationPrefix, $routePrefix, [
            'create_error' => $error,
            'open_create_modal' => true,
            'create_form' => $form,
        ]);
    }

    protected function updateRecord(
        int $id,
        Request $request,
        AuthSession $auth,
        SimpleCatalogRepository $repository,
        AuditLogger $audit,
        string $entityCode,
        string $translationPrefix,
        string $routePrefix,
    ): Response {
        $this->denyPermission($auth, $entityCode, RolePermission::ACTION_UPDATE);

        $record = $repository->findById($id);
        if ($record === null) {
            throw $this->createNotFoundException('Record not found.');
        }

        $form = $this->catalogForm($request);
        $form['code'] = $record->code;
        $form['active'] = $request->request->getBoolean('active');
        $error = $this->validateCatalogForm($form);
        $error ??= $this->validateExtraCatalogForm($form);

        if ($error === null) {
            $before = $this->catalogSnapshot($record);
            $repository->update($record, $form['code'], $form['name'], $form['active'], $this->extraCatalogAttributes($form));
            $this->afterUpdateRecord($record, $form, $repository);
            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_UPDATE,
                $entityCode,
                $record->id,
                $record->label(),
                $before,
                $this->catalogSnapshot($record),
            );
            $this->addFlash('success', 'success.updated');

            return $this->redirectToRoute($routePrefix . '_index');
        }

        return $this->renderIndex($auth, $repository, $entityCode, $translationPrefix, $routePrefix, [
            'edit_error' => $error,
            'open_edit_record_id' => $id,
            'edit_form' => $form,
        ]);
    }

    protected function toggleRecordStatus(
        int $id,
        Request $request,
        AuthSession $auth,
        SimpleCatalogRepository $repository,
        AuditLogger $audit,
        string $entityCode,
        string $translationPrefix,
        string $routePrefix,
    ): Response {
        $record = $repository->findById($id);
        if ($record === null) {
            return $this->redirectToRoute($routePrefix . '_index');
        }

        $permissionAction = $record->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE;
        $auditAction = $record->active ? 'deactivate' : 'activate';
        $this->denyPermission($auth, $entityCode, $permissionAction);

        $before = $this->catalogSnapshot($record);
        $repository->setActive($record, !$record->active);
        $audit->log(
            $request,
            $auth,
            $auditAction,
            $entityCode,
            $record->id,
            $record->label(),
            $before,
            $this->catalogSnapshot($record),
            ['status_toggle' => true],
        );
        $this->addFlash('success', $record->active ? 'success.activated' : 'success.deactivated');

        return $this->redirectToRoute($routePrefix . '_index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogForm(Request $request): array
    {
        return array_replace([
            'name' => trim((string) $request->request->get('name', '')),
        ], $this->extraCatalogForm($request));
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function validateCatalogForm(array $form): ?string
    {
        if ($form['name'] === '') {
            return 'error.required_catalog_fields';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function validateExtraCatalogForm(array $form): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogTemplateVariables(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogForm(Request $request): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function extraCatalogAttributes(array $form): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogSnapshot(SimpleCatalogModel $record): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function afterCreateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function afterUpdateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
    }

    protected function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogSnapshot(SimpleCatalogModel $record): array
    {
        return array_replace([
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->label(),
            'active' => (bool) $record->active,
        ], $this->extraCatalogSnapshot($record));
    }
}
