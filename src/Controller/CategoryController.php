<?php

namespace App\Controller;

use App\Entity\MenuCategory;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Form\CategoryType;
use App\Form\RestaurantType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('category')]
final class CategoryController extends AbstractController
{
    #[Route('/{id}/create', name: 'app_category_create')]
    public function create( Restaurant $restaurant, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $category = new MenuCategory();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $category->setRestaurant($restaurant);
            $entityManager->persist($category);
            $entityManager->flush();

            return $this->redirectToRoute('restaurant',['id' => $restaurant->getId()]);
        }
        return $this->render('category/create.html.twig', [
            'form' => $form->createView(),
            'restaurant' => $restaurant,
        ]);

    }

    #[Route('/delete/{id}', name: 'delete_category')]
    public function delete(MenuCategory $category, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $category->getRestaurant()->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }
        if(!$category){return $this->redirectToRoute('app_admin', ['id' => $category->getRestaurant()->getId()]);}

        $entityManager->remove($category);
        $entityManager->flush();
        return $this->redirectToRoute('restaurant',['id' => $category->getRestaurant()->getId()]);



    }
}
