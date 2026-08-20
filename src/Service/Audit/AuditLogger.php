<?php

namespace App\Service\Audit;

use App\Domain\Audit\Model\AuditLog;
use App\Service\Auth\AuthSession;
use App\Service\Eloquent\EloquentManager;
use Symfony\Component\HttpFoundation\Request;

final class AuditLogger
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /**
     * @param array<string, mixed>|null $beforeValues
     * @param array<string, mixed>|null $afterValues
     * @param array<string, mixed> $metadata
     */
    public function log(
        Request $request,
        AuthSession $auth,
        string $action,
        string $entityCode,
        ?int $entityId,
        ?string $entityLabel,
        ?array $beforeValues,
        ?array $afterValues,
        array $metadata = [],
    ): void {
        $this->eloquent->boot();

        $actor = $auth->user();
        $routeParameters = $request->attributes->get('_route_params', []);

        AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_role_code' => $actor?->role,
            'action' => $action,
            'entity_code' => $entityCode,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'before_values' => $beforeValues,
            'after_values' => $afterValues,
            'ip_address' => $request->getClientIp(),
            'user_agent' => $this->limitHeader($request->headers->get('User-Agent')),
            'request_method' => $request->getMethod(),
            'request_path' => $request->getPathInfo(),
            'route_name' => $request->attributes->get('_route'),
            'referer' => $this->limitHeader($request->headers->get('Referer')),
            'metadata' => array_replace([
                'locale' => $request->getLocale(),
                'query' => $request->query->all(),
                'route_parameters' => is_array($routeParameters) ? $routeParameters : [],
                'client_ips' => $request->getClientIps(),
                'remote_addr' => $request->server->get('REMOTE_ADDR'),
                'x_forwarded_for' => $this->limitHeader($request->headers->get('X-Forwarded-For')),
                'x_real_ip' => $this->limitHeader($request->headers->get('X-Real-IP')),
            ], $metadata),
        ]);
    }

    private function limitHeader(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, 512);
    }
}
