<?php

namespace App\Twig;

use App\Domain\Access\Repository\PermissionRepository;
use App\Service\Auth\AuthSession;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminPermissionExtension extends AbstractExtension
{
    public function __construct(private readonly AuthSession $auth)
    {
    }

    /**
     * @return array<int, TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_entity_locked', [$this, 'isEntityLocked']),
        ];
    }

    public function isEntityLocked(string $entityCode): bool
    {
        foreach (PermissionRepository::ACTIONS as $action) {
            if ($this->auth->can($entityCode, $action)) {
                return false;
            }
        }

        return true;
    }
}
