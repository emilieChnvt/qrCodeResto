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

#[Route('/restaurant')]
final class RestaurantController extends AbstractController
{
    #[Route('/{id}', name: 'restaurant')]
    public function show(Restaurant $restaurant, MenuCategoryRepository $categoryRepository): Response
    {
        if(!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()){
            return $this->redirectToRoute('app_login');
        }
        $categories = $categoryRepository->findByRestaurant($restaurant);

        return $this->render('restaurant/show.html.twig', [
            'restaurant' => $restaurant,
            'categories' => $categories
        ]);
    }

    #[Route('/create', name: 'app_create_restaurant')]
    public function create(EntityManagerInterface $entityManager, Request $request, MenuCategoryRepository $categoryRepository): Response
    {
        if(!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()){
            return $this->redirectToRoute('app_login');
        }
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
        $categories = $categoryRepository->findByRestaurant($restaurant);

        return $this->render('restaurant/create.html.twig', [
            'form' => $form->createView(),
            'categories' => $categories,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_delete_restaurant')]
    public function delete(Restaurant $restaurant, EntityManagerInterface $entityManager): Response
    {
        if(!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()){
            return $this->redirectToRoute('app_login');
        }
        $entityManager->remove($restaurant);
        $entityManager->flush();
        return $this->redirectToRoute('app_admin',['id' => $this->getUser()->getId()]);
    }

    #[Route('/update/{id}', name: 'app_update_restaurant')]
    public function edit(Restaurant $restaurant, Request $request, EntityManagerInterface $entityManager, MenuCategoryRepository $categoryRepository): Response
    {
        if(!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()){
            return $this->redirectToRoute('app_login');
        }
        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_admin',['id' => $this->getUser()->getId()]);
        }
        $categories = $categoryRepository->findByRestaurant($restaurant);
        return $this->render('restaurant/edit.html.twig', [
            'form' => $form->createView(),
            'restaurant' => $restaurant,
        ]);
    }
}
