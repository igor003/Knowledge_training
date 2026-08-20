<?php

namespace App\Controller\Admin;

use App\Domain\Role\Repository\RoleRepository;
use App\Domain\User\Repository\UserRepository;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    private const MIN_PASSWORD_LENGTH = 4;

    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthSession $auth, UserRepository $users): Response
    {
        if ($users->count() === 0) {
            return $this->redirectToRoute('admin_setup');
        }

        if ($auth->check()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $name = (string) $request->request->get('name', '');
            $password = (string) $request->request->get('password', '');

            $result = $auth->attempt($name, $password);

            if ($result === AuthSession::ATTEMPT_SUCCESS) {
                return $this->redirectToRoute('admin_dashboard');
            }

            $error = $result === AuthSession::ATTEMPT_INACTIVE
                ? 'error.inactive_account'
                : 'error.invalid_credentials';
        }

        return $this->render('admin/auth/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/admin/setup', name: 'admin_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request, AuthSession $auth, UserRepository $users, RoleRepository $roles): Response
    {
        if ($users->count() > 0) {
            return $this->redirectToRoute('admin_login');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');

            if ($name === '' || $email === '' || strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $error = 'error.required_user_fields';
            } else {
                $adminRole = $roles->findDefaultAdmin();

                if ($adminRole === null) {
                    $error = 'error.admin_role_missing';

                    return $this->render('admin/auth/setup.html.twig', [
                        'error' => $error,
                    ]);
                }

                $user = $users->create($name, $email, $password, $adminRole);
                $auth->login($user);

                return $this->redirectToRoute('admin_dashboard');
            }
        }

        return $this->render('admin/auth/setup.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(AuthSession $auth): RedirectResponse
    {
        $auth->logout();

        return $this->redirectToRoute('admin_login');
    }
}
