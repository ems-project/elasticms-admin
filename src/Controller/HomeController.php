<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     * @Route("/ems-admin", name="homepage_url")
     */
    public function indexAction(): RedirectResponse
    {
        return $this->redirectToRoute('notifications.inbox');
    }
}
