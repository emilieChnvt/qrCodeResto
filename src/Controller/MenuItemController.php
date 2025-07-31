<?php

namespace App\Controller;

use App\Entity\MenuCategory;
use App\Entity\MenuItem;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Form\MenuItemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuItemController extends AbstractController
{
    #[Route('/menu/item', name: 'app_menu_item')]
    public function index(): Response
    {
        return $this->render('menu_item/index.html.twig', [
            'controller_name' => 'MenuItemController',
        ]);
    }

    #[Route('/menu/item/{id}/create', name: 'app_menu_item_create')]
    public function create(EntityManagerInterface $entityManager, Request $request, MenuCategory $category): Response
    {
        if($this->getUser()->getId() !== $category->getRestaurant()->getOfUser()->getId()){
            return $this->redirectToRoute('restaurant',['id' => $category->getRestaurant()->getId()]);
        }
        if ($this->getUser()->getSubscriptionPlan() === 'free') {
            $this->addFlash('error', 'Vous devez être abonné pour utiliser cette fonctionnalité.');
            return $this->redirectToRoute('payment_index');
        }

        $menuItem = new MenuItem();
        $form = $this->createForm(MenuItemType::class, $menuItem);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $menuItem->setCategory($category);
            $entityManager->persist($menuItem);
            $entityManager->flush();
            return $this->redirectToRoute('restaurant',['id' => $menuItem->getCategory()->getRestaurant()->getId()]);
        }
        return $this->render('menu_item/create.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);


    }

    #[Route('/menu/item/delete/{id}', name: 'app_delete_menu_item')]
    public function delete(MenuItem $menuItem, EntityManagerInterface $entityManager, MenuCategory $category): Response
    {
        if($this->getUser()->getId() !== $category->getRestaurant()->getOfUser()->getId()){
            return $this->redirectToRoute('restaurant',['id' => $category->getRestaurant()->getId()]);
        }

        if ($this->getUser()->getSubscriptionPlan() === 'free') {
            $this->addFlash('error', 'Vous devez être abonné pour utiliser cette fonctionnalité.');
            return $this->redirectToRoute('payment_index');
        }


        $entityManager->remove($menuItem);
        $entityManager->flush();
        return $this->redirectToRoute('restaurant',['id' => $menuItem->getCategory()->getRestaurant()->getId()]);
    }

}
