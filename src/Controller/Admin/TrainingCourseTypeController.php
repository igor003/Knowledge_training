<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\TrainingCourse\Model\TrainingCourseType;
use App\Domain\TrainingCourse\Repository\TrainingCourseTypeRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingCourseTypeController extends AbstractController
{
    #[Route('/admin/training-course-types', name: 'admin_training_course_types_index', methods: ['GET'])]
    public function index(AuthSession $auth, TrainingCourseTypeRepository $types): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_READ);

        return $this->renderIndex($auth, $types);
    }

    #[Route('/admin/training-course-types/create', name: 'admin_training_course_types_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, TrainingCourseTypeRepository $types, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_CREATE);

        $form = $this->typeForm($request);
        $error = $this->validateTypeForm($form);

        if ($error === null) {
            $type = $types->create($form['name'], $form['description']);

            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_CREATE,
                PermissionRepository::ENTITY_TRAINING_COURSE_TYPES,
                $type->id,
                $type->label(),
                null,
                $this->snapshot($type),
            );
            $this->addFlash('success', 'success.created');

            return $this->redirectToRoute('admin_training_course_types_index');
        }

        return $this->renderIndex($auth, $types, [
            'create_error' => $error,
            'open_create_modal' => true,
            'create_form' => $form,
        ]);
    }

    #[Route('/admin/training-course-types/{id}/update', name: 'admin_training_course_types_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, TrainingCourseTypeRepository $types, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_UPDATE);

        $type = $types->findById($id);
        if ($type === null) {
            throw $this->createNotFoundException('Training course type not found.');
        }

        $form = $this->typeForm($request);
        $error = $this->validateTypeForm($form);

        if ($error === null) {
            $before = $this->snapshot($type);
            $types->update($type, $form['name'], $form['description']);

            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_UPDATE,
                PermissionRepository::ENTITY_TRAINING_COURSE_TYPES,
                $type->id,
                $type->label(),
                $before,
                $this->snapshot($type),
            );
            $this->addFlash('success', 'success.updated');

            return $this->redirectToRoute('admin_training_course_types_index');
        }

        return $this->renderIndex($auth, $types, [
            'edit_error' => $error,
            'open_edit_type_id' => $id,
            'edit_form' => $form,
        ]);
    }

    #[Route('/admin/training-course-types/{id}/toggle-status', name: 'admin_training_course_types_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, TrainingCourseTypeRepository $types, AuditLogger $audit): Response
    {
        $type = $types->findById($id);
        if ($type === null) {
            return $this->redirectToRoute('admin_training_course_types_index');
        }

        $permissionAction = $type->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE;
        $auditAction = $type->active ? 'deactivate' : 'activate';
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, $permissionAction);

        $before = $this->snapshot($type);
        $types->setActive($type, !$type->active);

        $audit->log(
            $request,
            $auth,
            $auditAction,
            PermissionRepository::ENTITY_TRAINING_COURSE_TYPES,
            $type->id,
            $type->label(),
            $before,
            $this->snapshot($type),
            ['status_toggle' => true],
        );
        $this->addFlash('success', $type->active ? 'success.activated' : 'success.deactivated');

        return $this->redirectToRoute('admin_training_course_types_index');
    }

    #[Route('/admin/training-course-types/{id}/delete', name: 'admin_training_course_types_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, TrainingCourseTypeRepository $types, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_DELETE);

        $type = $types->findById($id);
        if ($type === null) {
            return $this->redirectToRoute('admin_training_course_types_index');
        }

        $before = $this->snapshot($type);
        $audit->log(
            $request,
            $auth,
            RolePermission::ACTION_DELETE,
            PermissionRepository::ENTITY_TRAINING_COURSE_TYPES,
            $type->id,
            $type->label(),
            $before,
            null,
            ['physical_delete' => true],
        );

        $types->physicallyDelete($type);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute('admin_training_course_types_index');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderIndex(AuthSession $auth, TrainingCourseTypeRepository $types, array $overrides = []): Response
    {
        return $this->render('admin/training_course_types/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'types' => $types->list(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_DEACTIVATE),
            'can_delete' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_TYPES, RolePermission::ACTION_DELETE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => $this->emptyForm(),
            'edit_error' => null,
            'open_edit_type_id' => null,
            'edit_form' => [],
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyForm(): array
    {
        return [
            'name' => '',
            'description' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeForm(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('name', '')),
            'description' => $this->nullableText((string) $request->request->get('description', '')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function validateTypeForm(array $form): ?string
    {
        if ($form['name'] === '') {
            return 'error.required_training_course_type_fields';
        }

        return null;
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TrainingCourseType $type): array
    {
        $type->loadMissing('courses');

        return [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->label(),
            'description' => $type->description,
            'course_ids' => $type->courses
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            'courses' => $type->courses
                ->map(static fn ($course): string => $course->label())
                ->all(),
            'active' => (bool) $type->active,
        ];
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
