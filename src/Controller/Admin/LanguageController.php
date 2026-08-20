<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LanguageController extends AbstractController
{
    private const SUPPORTED_LOCALES = ['ru', 'ro', 'it', 'fr'];

    #[Route('/admin/language/{locale}', name: 'admin_language_switch', methods: ['GET'])]
    public function switch(string $locale, Request $request): RedirectResponse
    {
        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $request->getSession()->set('admin_locale', $locale);
        }

        $referer = $request->headers->get('referer');

        return new RedirectResponse($referer ?: $this->generateUrl('admin_dashboard'));
    }
}
