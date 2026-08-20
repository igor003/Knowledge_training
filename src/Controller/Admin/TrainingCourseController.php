<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\FactoryDepartment\Repository\FactoryDepartmentRepository;
use App\Domain\TrainingCourse\Model\TrainingCourse;
use App\Domain\TrainingCourse\Model\TrainingCourseAssessmentMethod;
use App\Domain\TrainingCourse\Repository\TrainingCourseAssessmentMethodRepository;
use App\Domain\TrainingCourse\Repository\TrainingCourseRepository;
use App\Domain\TrainingCourse\Repository\TrainingCourseTypeRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingCourseController extends AbstractController
{
    #[Route('/admin/training-courses', name: 'admin_training_courses_index', methods: ['GET'])]
    public function index(AuthSession $auth, TrainingCourseRepository $courses, FactoryDepartmentRepository $departments, TrainingCourseTypeRepository $types, TrainingCourseAssessmentMethodRepository $assessmentMethods): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSES, RolePermission::ACTION_READ);

        return $this->renderIndex($auth, $courses, $departments, $types, $assessmentMethods);
    }

    #[Route('/admin/training-courses/create', name: 'admin_training_courses_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, TrainingCourseRepository $courses, FactoryDepartmentRepository $departments, TrainingCourseTypeRepository $types, TrainingCourseAssessmentMethodRepository $assessmentMethods, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSES, RolePermission::ACTION_CREATE);

        $form = $this->courseForm($request, $departments, $types, $assessmentMethods);
        $error = $this->validateCourseForm($form);

        if ($error === null) {
            $course = $courses->create(
                $form['name'],
                $form['description'],
                $form['duration_hours'],
                $form['periodicity_months'],
                $form['assessment_method_id'],
                $form['assessment_method'],
                $form['department_ids'],
                $form['type_id'],
            );

            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_CREATE,
                PermissionRepository::ENTITY_TRAINING_COURSES,
                $course->id,
                $course->label(),
                null,
                $this->snapshot($course),
            );
            $this->addFlash('success', 'success.created');

            return $this->redirectToRoute('admin_training_courses_index');
        }

        return $this->renderIndex($auth, $courses, $departments, $types, $assessmentMethods, [
            'create_error' => $error,
            'open_create_modal' => true,
            'create_form' => $form,
        ]);
    }

    #[Route('/admin/training-courses/{id}/update', name: 'admin_training_courses_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, TrainingCourseRepository $courses, FactoryDepartmentRepository $departments, TrainingCourseTypeRepository $types, TrainingCourseAssessmentMethodRepository $assessmentMethods, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSES, RolePermission::ACTION_UPDATE);

        $course = $courses->findById($id);
        if ($course === null) {
            throw $this->createNotFoundException('Training course not found.');
        }

        $form = $this->courseForm($request, $departments, $types, $assessmentMethods);
        $form['active'] = $request->request->getBoolean('active');
        $error = $this->validateCourseForm($form);

        if ($error === null) {
            $before = $this->snapshot($course);
            $courses->update(
                $course,
                $form['name'],
                $form['description'],
                $form['duration_hours'],
                $form['periodicity_months'],
                $form['assessment_method_id'],
                $form['assessment_method'],
                $form['active'],
                $form['department_ids'],
                $form['type_id'],
            );

            $audit->log(
                $request,
                $auth,
                RolePermission::ACTION_UPDATE,
                PermissionRepository::ENTITY_TRAINING_COURSES,
                $course->id,
                $course->label(),
                $before,
                $this->snapshot($course),
            );
            $this->addFlash('success', 'success.updated');

            return $this->redirectToRoute('admin_training_courses_index');
        }

        return $this->renderIndex($auth, $courses, $departments, $types, $assessmentMethods, [
            'edit_error' => $error,
            'open_edit_course_id' => $id,
            'edit_form' => $form,
        ]);
    }

    #[Route('/admin/training-courses/{id}/toggle-status', name: 'admin_training_courses_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, TrainingCourseRepository $courses, FactoryDepartmentRepository $departments, AuditLogger $audit): Response
    {
        $course = $courses->findById($id);
        if ($course === null) {
            return $this->redirectToRoute('admin_training_courses_index');
        }

        $permissionAction = $course->active ? RolePermission::ACTION_DEACTIVATE : RolePermission::ACTION_UPDATE;
        $auditAction = $course->active ? 'deactivate' : 'activate';
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSES, $permissionAction);

        $before = $this->snapshot($course);
        $courses->setActive($course, !$course->active);

        $audit->log(
            $request,
            $auth,
            $auditAction,
            PermissionRepository::ENTITY_TRAINING_COURSES,
            $course->id,
            $course->label(),
            $before,
            $this->snapshot($course),
            ['status_toggle' => true],
        );
        $this->addFlash('success', $course->active ? 'success.activated' : 'success.deactivated');

        return $this->redirectToRoute('admin_training_courses_index');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderIndex(AuthSession $auth, TrainingCourseRepository $courses, FactoryDepartmentRepository $departments, TrainingCourseTypeRepository $types, TrainingCourseAssessmentMethodRepository $assessmentMethods, array $overrides = []): Response
    {
        return $this->render('admin/training_courses/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'courses' => $courses->list(),
            'departments' => $departments->listActive(),
            'types' => $types->listActive(),
            'assessment_methods' => $assessmentMethods->listActive(),
            'can_create' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSES, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSES, RolePermission::ACTION_UPDATE),
            'can_deactivate' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSES, RolePermission::ACTION_DEACTIVATE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => $this->emptyForm(),
            'edit_error' => null,
            'open_edit_course_id' => null,
            'edit_form' => [],
            'action_error' => null,
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
            'duration_hours' => '',
            'periodicity_months' => '',
            'assessment_method_id' => '',
            'assessment_method' => '',
            'department_ids' => [],
            'type_id' => '',
            'active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseForm(Request $request, FactoryDepartmentRepository $departments, TrainingCourseTypeRepository $types, TrainingCourseAssessmentMethodRepository $assessmentMethods): array
    {
        $periodicity = trim((string) $request->request->get('periodicity_months', ''));
        $duration = trim(str_replace(',', '.', (string) $request->request->get('duration_hours', '')));
        $assessmentMethod = $this->activeAssessmentMethodFromRequest((string) $request->request->get('assessment_method_id', ''), $assessmentMethods);

        return [
            'name' => trim((string) $request->request->get('name', '')),
            'description' => $this->nullableText((string) $request->request->get('description', '')),
            'duration_hours' => $duration !== '' && is_numeric($duration) ? (float) $duration : ($duration === '' ? null : -1.0),
            'periodicity_months' => $periodicity !== '' ? (int) $periodicity : null,
            'assessment_method_id' => $assessmentMethod?->id,
            'assessment_method' => $assessmentMethod !== null ? (string) $assessmentMethod->code : '',
            'department_ids' => $this->activeDepartmentIdsFromRequest((array) $request->request->all('department_ids'), $departments),
            'type_id' => $this->activeTypeIdFromRequest((string) $request->request->get('type_id', ''), $types),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function validateCourseForm(array $form): ?string
    {
        if ($form['name'] === '' || $form['assessment_method_id'] === null) {
            return 'error.required_training_course_fields';
        }

        if ($form['periodicity_months'] !== null && ($form['periodicity_months'] < 1 || $form['periodicity_months'] > 1200)) {
            return 'error.invalid_training_course_periodicity';
        }

        if ($form['duration_hours'] !== null && ($form['duration_hours'] <= 0 || $form['duration_hours'] > 1000)) {
            return 'error.invalid_training_course_duration';
        }

        if ($form['department_ids'] === []) {
            return 'error.required_training_course_departments';
        }

        return null;
    }

    private function nullableText(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<int, mixed> $departmentIds
     * @return array<int, int>
     */
    private function activeDepartmentIdsFromRequest(array $departmentIds, FactoryDepartmentRepository $departments): array
    {
        $activeDepartmentIds = [];
        foreach ($departments->listActive() as $department) {
            $activeDepartmentIds[(int) $department->id] = true;
        }

        $allowedIds = [];
        foreach ($departmentIds as $departmentId) {
            $id = (int) $departmentId;
            if ($id > 0 && isset($activeDepartmentIds[$id])) {
                $allowedIds[$id] = $id;
            }
        }

        return array_values($allowedIds);
    }

    private function activeTypeIdFromRequest(string $typeId, TrainingCourseTypeRepository $types): ?int
    {
        $id = (int) $typeId;
        if ($id <= 0) {
            return null;
        }

        $activeTypeIds = [];
        foreach ($types->listActive() as $type) {
            $activeTypeIds[(int) $type->id] = true;
        }

        return isset($activeTypeIds[$id]) ? $id : null;
    }

    private function activeAssessmentMethodFromRequest(string $methodId, TrainingCourseAssessmentMethodRepository $methods): ?TrainingCourseAssessmentMethod
    {
        $id = (int) $methodId;
        if ($id <= 0) {
            return null;
        }

        foreach ($methods->listActive() as $method) {
            if ((int) $method->id === $id) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TrainingCourse $course): array
    {
        $course->loadMissing('departments', 'type', 'assessmentMethod');

        return [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->label(),
            'description' => $course->description,
            'duration_hours' => $course->duration_hours,
            'periodicity_months' => $course->periodicity_months,
            'assessment_method_id' => $course->assessmentMethodId(),
            'assessment_method' => $course->assessmentMethodLabel(),
            'assessment_method_code' => $course->assessment_method,
            'factory_department_ids' => $course->departmentIds(),
            'factory_departments' => $course->departmentLabels(),
            'type_id' => $course->typeId(),
            'type' => $course->typeLabel(),
            'active' => (bool) $course->active,
        ];
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
