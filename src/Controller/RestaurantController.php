<?php

namespace App\Controller;

use App\Entity\MenuCategory;
use App\Entity\Restaurant;
use App\Form\RestaurantType;
use App\Repository\MenuCategoryRepository;
use App\Repository\RestaurantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RestaurantController extends AbstractController
{
    #[Route('/restaurant/{id}', name: 'restaurant')]
    public function show(Restaurant $restaurant, MenuCategoryRepository $categoryRepository): Response
    {
        return $this->render('restaurant/show.html.twig', [
            'restaurant' => $restaurant,
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/create/restaurant', name: 'app_create_restaurant')]
    public function create(EntityManagerInterface $entityManager, Request $request, MenuCategoryRepository $categoryRepository): Response
    {
        if(!$this->getUser()){return $this->redirectToRoute('app_login');}

        $restaurant = new Restaurant();
        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $restaurant->setCreateAt(new \DateTimeImmutable());
            $restaurant->setOfUser($this->getUser());
            $entityManager->persist($restaurant);
            $entityManager->flush();

            $categoryNames = ['Entrées', 'Plats', 'Desserts', 'Boissons', 'Cocktails', 'Digestifs', 'Boissons chaudes', 'Menus'];

            foreach ($categoryNames as $name) {
                $category = new MenuCategory();
                $category->setRestaurant($restaurant);
                $category->setName($name);
                $entityManager->persist($category);
            }
            $entityManager->flush();


            return $this->redirectToRoute('app_admin',['id' => $this->getUser()->getId()]);
        }

        return $this->render('restaurant/create.html.twig', [
            'form' => $form->createView(),
            'categories' => $categoryRepository->findAll(),
        ]);
    }
}
