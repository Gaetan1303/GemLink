<?php

namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class AuthController extends AbstractController
{
    #[Route('/auth/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, AuthService $authService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $authService->register($data);
        
        return $this->json(['message' => 'Inscription réussie. Veuillez valider votre email.'], Response::HTTP_CREATED);
    }
}