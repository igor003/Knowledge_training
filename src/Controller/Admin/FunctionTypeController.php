<?php

namespace App\Controller\Admin;

use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\FunctionType\Repository\FunctionTypeRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FunctionTypeController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'factory_function_types';
    private const ROUTE_PREFIX = 'admin_factory_function_types';

    #[Route('/admin/factory-function-types', name: 'admin_factory_function_types_index', methods: ['GET'])]
    public function index(AuthSession $auth, FunctionTypeRepository $types): Response
    {
        return $this->renderIndex($auth, $types, PermissionRepository::ENTITY_FACTORY_FUNCTION_TYPES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-function-types/create', name: 'admin_factory_function_types_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, FunctionTypeRepository $types, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $types, $audit, PermissionRepository::ENTITY_FACTORY_FUNCTION_TYPES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-function-types/{id}/update', name: 'admin_factory_function_types_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, FunctionTypeRepository $types, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $types, $audit, PermissionRepository::ENTITY_FACTORY_FUNCTION_TYPES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-function-types/{id}/toggle-status', name: 'admin_factory_function_types_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, FunctionTypeRepository $types, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $types, $audit, PermissionRepository::ENTITY_FACTORY_FUNCTION_TYPES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    protected function catalogTemplateVariables(): array
    {
        return [
            'show_function_count' => true,
            'show_column_filters' => true,
        ];
    }
}
