<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\TrainingCourse\Model\TrainingCourseAssessmentMethod;
use App\Domain\TrainingCourse\Repository\TrainingCourseAssessmentMethodRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingCourseAssessmentMethodController extends AbstractController
{
    #[Route('/admin/training-course-assessment-methods', name: 'admin_training_course_assessment_methods_index', methods: ['GET'])]
    public function index(AuthSession $auth, TrainingCourseAssessmentMethodRepository $methods): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, RolePermission::ACTION_READ);

        return $this->renderIndex($auth, $methods);
    }

    #[Route('/admin/training-course-assessment-methods/create', name: 'admin_training_course_assessment_methods_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, TrainingCourseAssessmentMethodRepository $methods, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, RolePermission::ACTION_CREATE);

        $form = $this->methodForm($request);
        $error = $this->validateMethodForm($form);

        if ($error === null) {
            $method = $methods->create($form['name'], $form['description']);

            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_CREATE,
                PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS,
                $method->id,
                $method->label(),
                null,
                $this->snapshot($method),
            );
            $this->addFlash('success', 'success.created');

            return $this->redirectToRoute('admin_training_course_assessment_methods_index');
        }

        return $this->renderIndex($auth, $methods, [
            'create_error' => $error,
            'open_create_modal' => true,
            'create_form' => $form,
        ]);
    }

    #[Route('/admin/training-course-assessment-methods/{id}/update', name: 'admin_training_course_assessment_methods_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, TrainingCourseAssessmentMethodRepository $methods, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, RolePermission::ACTION_UPDATE);

        $method = $methods->findById($id);
        if ($method === null) {
            throw $this->createNotFoundException('Training course assessment method not found.');
        }

        $form = $this->methodForm($request);
        $error = $this->validateMethodForm($form);

        if ($error === null) {
            $before = $this->snapshot($method);
            $methods->update($method, $form['name'], $form['description']);

            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_UPDATE,
                PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS,
                $method->id,
                $method->label(),
                $before,
                $this->snapshot($method),
            );
            $this->addFlash('success', 'success.updated');

            return $this->redirectToRoute('admin_training_course_assessment_methods_index');
        }

        return $this->renderIndex($auth, $methods, [
            'edit_error' => $error,
            'open_edit_method_id' => $id,
            'edit_form' => $form,
        ]);
    }

    #[Route('/admin/training-course-assessment-methods/{id}/toggle-status', name: 'admin_training_course_assessment_methods_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, TrainingCourseAssessmentMethodRepository $methods, AuditLogger $audit): Response
    {
        $method = $methods->findById($id);
        if ($method === null) {
            return $this->redirectToRoute('admin_training_course_assessment_methods_index');
        }

        $permissionAction = $method->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE;
        $auditAction = $method->active ? 'deactivate' : 'activate';
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, $permissionAction);

        $before = $this->snapshot($method);
        $methods->setActive($method, !$method->active);

        $audit->log(
            $request,
            $auth,
            $auditAction,
            PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS,
            $method->id,
            $method->label(),
            $before,
            $this->snapshot($method),
            ['status_toggle' => true],
        );
        $this->addFlash('success', $method->active ? 'success.activated' : 'success.deactivated');

        return $this->redirectToRoute('admin_training_course_assessment_methods_index');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderIndex(AuthSession $auth, TrainingCourseAssessmentMethodRepository $methods, array $overrides = []): Response
    {
        return $this->render('admin/training_course_assessment_methods/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'methods' => $methods->list(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_ASSESSMENT_METHODS, RolePermission::ACTION_DEACTIVATE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => $this->emptyForm(),
            'edit_error' => null,
            'open_edit_method_id' => null,
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
    private function methodForm(Request $request): array
    {
        return [
            'name' => trim((string) $request->request->get('name', '')),
            'description' => $this->nullableText((string) $request->request->get('description', '')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function validateMethodForm(array $form): ?string
    {
        if ($form['name'] === '') {
            return 'error.required_training_course_assessment_method_fields';
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
    private function snapshot(TrainingCourseAssessmentMethod $method): array
    {
        $method->loadMissing('courses');

        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->label(),
            'description' => $method->description,
            'course_ids' => $method->courses
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            'courses' => $method->courses
                ->map(static fn ($course): string => $course->label())
                ->all(),
            'active' => (bool) $method->active,
        ];
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
