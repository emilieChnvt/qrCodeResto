<?php

namespace App\Controller;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\RestaurantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/{id}', name: 'app_admin')]
    public function index(User $user, RestaurantRepository $restaurantRepository): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $restaurant = $restaurantRepository->find($user->getId());
        return $this->render('admin/index.html.twig', [
            'restaurants' => $restaurantRepository->findAll(),
            'restaurant' => $restaurant,
        ]);
    }
}
