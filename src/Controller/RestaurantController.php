<?php

namespace App\Controller;

use App\Entity\MenuCategory;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Form\RestarantMenuUploadType;
use App\Form\RestaurantType;
use App\Repository\MenuCategoryRepository;
use App\Repository\RestaurantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\BuilderInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/restaurant')]
final class RestaurantController extends AbstractController
{
    #[Route('/{id}', name: 'restaurant')]
    public function show(Restaurant $restaurant,RestaurantRepository $restaurantRepository, MenuCategoryRepository $categoryRepository): Response
    {
        if(!$this->getUser() || $this->getUser()->getId() !== $restaurant->getOfUser()->getId()){
            return $this->redirectToRoute('app_login');
        }
        $categories = $categoryRepository->findByRestaurant($restaurant);

        return $this->render('restaurant/show.html.twig', [
            'restaurant' => $restaurant,
            'categories' => $categories,
            'restaurants'=>$restaurantRepository->findAll()
        ]);
    }

    #[Route('/create/{id}', name: 'app_create_restaurant')]
    public function create(EntityManagerInterface $entityManager, Request $request, MenuCategoryRepository $categoryRepository, User $user): Response
    {
        if(!$this->getUser()){
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

            $categoryNames = ['Boissons', 'Boissons chaudes', 'Cocktails','Entrées', 'Plats',   'Menus', 'Desserts', 'Digestifs'];

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





    #[Route('/qr-code/{id}/{type}', name: 'app_qr_code')]
    public function qrCode(
        Restaurant $restaurant,
        BuilderInterface $builder,
        RequestStack $requestStack,
        string $type = 'manual' // valeur par défaut
    ): Response
    {
        $request = $requestStack->getCurrentRequest();
        $baseUrl = $request->getSchemeAndHttpHost();

        if ($type === 'pdf') {
            if (!$restaurant->getMenuFileName()) {
                throw $this->createNotFoundException('Aucun menu uploadé trouvé.');
            }
            $url = $baseUrl . '/uploads/menus/' . $restaurant->getMenuFileName();
        } else {
            // URL du menu manuel
            $url = $this->generateUrl('app_show_menu', ['id' => $restaurant->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $result = $builder->build(data: $url, size: 300, margin: 10);
        $imageData = base64_encode($result->getString());
        $imageSrc = 'data:' . $result->getMimeType() . ';base64,' . $imageData;

        return $this->render('restaurant/show_qr.html.twig', [
            'qrCode' => $imageSrc,
            'type' => $type,
        ]);
    }





}
