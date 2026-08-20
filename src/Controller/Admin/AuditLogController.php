<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Audit\Repository\AuditLogRepository;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AuditLogController extends AbstractController
{
    #[Route('/admin/audit-logs', name: 'admin_audit_logs_index', methods: ['GET'])]
    public function index(AuthSession $auth, AuditLogRepository $auditLogs): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_AUDIT_LOGS, RolePermission::ACTION_READ);

        return $this->render('admin/audit_logs/index.html.twig', [
            'current_user' => $auth->user(),
            'logs' => $auditLogs->latest(),
        ]);
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
