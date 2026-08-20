<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminAccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!$exception instanceof AccessDeniedHttpException || !str_starts_with($path, '/admin')) {
            return;
        }

        if (in_array($path, ['/admin/login', '/admin/setup'], true)) {
            return;
        }

        $request->getSession()->getFlashBag()->add('error', 'error.permission_denied');

        $target = $request->isMethodSafe()
            ? $this->urlGenerator->generate('admin_dashboard')
            : ($request->headers->get('referer') ?: $this->urlGenerator->generate('admin_dashboard'));

        $event->setResponse(new RedirectResponse($target));
    }
}
