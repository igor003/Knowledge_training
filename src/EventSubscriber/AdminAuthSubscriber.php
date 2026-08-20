<?php

namespace App\EventSubscriber;

use App\Domain\User\Repository\UserRepository;
use App\Service\Auth\AuthSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthSession $auth,
        private readonly UserRepository $users,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/admin')) {
            return;
        }

        if (in_array($path, ['/admin/login', '/admin/setup'], true) || str_starts_with($path, '/admin/language/')) {
            return;
        }

        if ($this->users->count() === 0) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_setup')));

            return;
        }

        if (!$this->auth->check()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_login')));
        }
    }
}
