<?php

namespace App\Controller\Admin;

use App\Domain\User\Repository\UserRepository;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(AuthSession $auth, UserRepository $users): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'current_user' => $auth->user(),
            'users_count' => $users->count(),
        ]);
    }
}
