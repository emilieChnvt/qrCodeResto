<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('category')]
final class CategoryController extends AbstractController
{
    #[Route('/create/{id}', name: 'app_category_create')]
    public function index(): Response
    {

    }
}
