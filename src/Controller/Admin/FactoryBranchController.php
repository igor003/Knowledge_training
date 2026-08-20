<?php

namespace App\Controller\Admin;

use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\FactoryBranch\Repository\FactoryBranchRepository;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FactoryBranchController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'factory_branches';
    private const ROUTE_PREFIX = 'admin_factory_branches';

    #[Route('/admin/factory-branches', name: 'admin_factory_branches_index', methods: ['GET'])]
    public function index(AuthSession $auth, FactoryBranchRepository $branches): Response
    {
        return $this->renderIndex($auth, $branches, PermissionRepository::ENTITY_FACTORY_BRANCHES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-branches/create', name: 'admin_factory_branches_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, FactoryBranchRepository $branches, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $branches, $audit, PermissionRepository::ENTITY_FACTORY_BRANCHES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-branches/{id}/update', name: 'admin_factory_branches_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, FactoryBranchRepository $branches, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $branches, $audit, PermissionRepository::ENTITY_FACTORY_BRANCHES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-branches/{id}/toggle-status', name: 'admin_factory_branches_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, FactoryBranchRepository $branches, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $branches, $audit, PermissionRepository::ENTITY_FACTORY_BRANCHES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogTemplateVariables(): array
    {
        return [
            'catalog_name_label' => 'catalog.city',
            'show_address' => true,
            'show_alias' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogForm(Request $request): array
    {
        return [
            'address' => trim((string) $request->request->get('address', '')),
            'alias' => trim((string) $request->request->get('alias', '')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function validateExtraCatalogForm(array $form): ?string
    {
        if ((string) ($form['address'] ?? '') === '') {
            return 'error.required_factory_branch_fields';
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
            'address' => (string) ($form['address'] ?? ''),
            'alias' => (($alias = trim((string) ($form['alias'] ?? ''))) !== '') ? $alias : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogSnapshot(SimpleCatalogModel $record): array
    {
        return [
            'address' => $record->getAttribute('address'),
            'alias' => $record->getAttribute('alias'),
        ];
    }
}
