<?php

namespace App\Controller;

use App\Entity\Profile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile/{id}', name: 'app_profile')]
    public function index(Profile $profile): Response
    {

        return $this->render('profile/index.html.twig', [
            'profile' => $profile,
        ]);
    }
}
