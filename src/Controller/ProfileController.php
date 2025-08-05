<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile/{id}', name: 'app_profile')]
    public function index( Profile $profile, EntityManagerInterface $manager, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        if(!$this->getUser() || $this->getUser()->getId() != $profile->getOfUser()->getId()){
            return $this->redirectToRoute('app_login');
        }


        $form = $this->createForm(UserType::class, $profile->getOfUser());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            if($newPassword){
                $hashedPassword = $passwordHasher->hashPassword($profile->getOfUser(), $newPassword);
                $profile->getOfUser()->setPassword($hashedPassword);
                $manager->persist($profile->getOfUser());
                $manager->flush();
            }
            $manager->persist($profile);
            $manager->flush();

            $this->addFlash('success', 'Profile modifié !');
            return $this->redirectToRoute('app_profile', ['id' => $profile->getId()]);
        }


        return $this->render('profile/index.html.twig', [
            'profile' => $profile,
            'form' => $form,
        ]);
    }


}
