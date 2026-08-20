<?php

namespace App\Controller;

use App\Service\Eloquent\EloquentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EloquentManager $eloquent): Response
    {
        $eloquent->boot();

        return $this->render('app/index.html.twig');
    }
}
