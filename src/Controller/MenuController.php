<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Restaurant;
use App\Form\AddItemToMenuType;
use App\Form\MenuType;
use App\Form\RestarantMenuUploadType;
use App\Repository\MenuCategoryRepository;
use App\Repository\MenuItemRepository;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/carte/{id}', name: 'app_show_menu')]
    public function publicMenuHTML(Restaurant $restaurant, MenuCategoryRepository $menuCategoryRepository): Response
    {
        $categories = $menuCategoryRepository->findByRestaurant($restaurant);

        return $this->render('restaurant/menu.html.twig', [
            'restaurant' => $restaurant,
            'categories' => $categories,
        ]);
    }


    #[Route('/menu/upload/{id}', name: 'app_upload_menu')]
    public function upload(Restaurant $restaurant, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(RestarantMenuUploadType::class, $restaurant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($restaurant);
            $entityManager->flush();

            return $this->redirectToRoute('restaurant', ['id' => $restaurant->getId()]);
        }

        return $this->render('restaurant/upload_menu.html.twig', [
            'form' => $form->createView(),
            'restaurant' => $restaurant,
        ]);
    }

    #[Route('/menu/create/{id}', name: 'menu_create_restaurant')]
    public function createMenu(Restaurant $restaurant,EntityManagerInterface $entityManager, Request $request, MenuCategoryRepository $menuCategoryRepository): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $categories = $menuCategoryRepository->findByRestaurant($restaurant);

        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu, [
            'categories' => $categories,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $menu->setRestaurant($restaurant);
            $entityManager->persist($menu);
            $entityManager->flush();

            return $this->redirectToRoute('add_menu_item', ['id' => $menu->getId()]);
        }



        return $this->render('restaurant/munuCreate.html.twig', [
            'form' => $form,
            'restaurant' => $restaurant,
            'categories' => $categories,
        ]);

    }

    #[Route('/menu/delete/{id}', name: 'app_delete_menu')]
    public function deleteMenu(Menu $menu, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $menu->getRestaurant()->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }
        $entityManager->remove($menu);
        $entityManager->flush();
        return $this->redirectToRoute('restaurant', ['id' => $menu->getRestaurant()->getId()]);
    }
    #[Route('/menu/addItem/{id}', name: 'add_menu_item')]
    public function addItemToMenu(MenuItemRepository $menuItemRepository, Menu $menu, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getId() !== $menu->getRestaurant()->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer tous les MenuItem
        $menuItems =$menuItemRepository->findAll();

        // Préparer les choix pour le formulaire
        $choices = [];
        foreach ($menuItems as $item) {
            $choices[$item->getName()] = $item->getId();
        }

        $form = $this->createForm(AddItemToMenuType::class, null, [
            'choices' => $choices,
            'selected' => [], // <- aucun item précoché
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $selectedItemIds = $form->get('menuItems')->getData();

            foreach ($selectedItemIds as $itemId) {
                $item = $menuItemRepository->find($itemId);
                if ($item) {
                    $menu->addMenuItem($item);
                }
            }

            $entityManager->persist($menu);
            $entityManager->flush();

            return $this->redirectToRoute('restaurant', ['id' => $menu->getRestaurant()->getId()]);
        }

        return $this->render('restaurant/addItemToMenu.html.twig', [
            'form' => $form,
            'menu' => $menu,
            'categories' => $menu->getCategories(),
        ]);
    }

    #[Route('/menu/edit/{id}', name: 'app_edit_menu')]
    public function edit(Menu $menu, EntityManagerInterface $entityManager, Request $request, MenuItemRepository $menuItemRepository): Response
    {
        if (!$this->getUser() || $this->getUser()->getId() !== $menu->getRestaurant()->getOfUser()->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $menuItems = $menuItemRepository->findAll();

        $choices = [];
        foreach ($menuItems as $item) {
            $choices[$item->getName()] = $item->getId();
        }

        // Obtenir les plats déjà dans le menu
        $selectedItemIds = $menu->getMenuItems()->map(fn($item) => $item->getId())->toArray();

        $form = $this->createForm(AddItemToMenuType::class, null, [
            'choices' => $choices,
            'selected' => $selectedItemIds, // <- ici on remplit
        ]);


        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedItemIds = $form->get('menuItems')->getData();


            foreach ($selectedItemIds as $itemId) {
                $item = $menuItemRepository->find($itemId);
                if ($item) {
                    $menu->addMenuItem($item);
                }
            }

            $entityManager->persist($menu);
            $entityManager->flush();

            return $this->redirectToRoute('restaurant', ['id' => $menu->getRestaurant()->getId()]);
        }

        return $this->render('menu/edit.html.twig', [
            'form' => $form,
            'menu' => $menu,
            'categories' => $menu->getCategories(),
        ]);
    }


}
