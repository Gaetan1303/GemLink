<?php

namespace App\Controller;

use App\Exception\LoginFailedException;
use App\Exception\LoginThrottledException;
use App\Service\AuthService;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/auth/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, AuthService $authService): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $authService->register($data);
        } catch (InvalidArgumentException $e) {
            // CA-3 : message générique
            return $this->json(
                ['message' => 'Si ces informations sont valides, un email de confirmation a été envoyé.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // CA-3 : même réponse en cas de succès réel ou de conflit email/username
        return $this->json(
            ['message' => 'Si ces informations sont valides, un email de confirmation a été envoyé.'],
            Response::HTTP_CREATED
        );
    }

    #[Route('/auth/validate-email/{token}', name: 'app_validate_email', methods: ['GET'])]
    public function validateEmail(string $token, AuthService $authService): JsonResponse
    {
        try {
            $authService->validateEmail($token);
        } catch (InvalidArgumentException $e) {
            return $this->json(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->json([
            'message' => AuthService::EMAIL_VALIDATION_SUCCESS_MESSAGE,
            'redirectTo' => '/auth/login?validated=1',
        ]);
    }

    #[Route('/auth/resend-validation-email', name: 'app_resend_validation_email', methods: ['POST'])]
    public function resendValidationEmail(
        Request $request,
        AuthService $authService,
        #[Autowire(service: 'limiter.email_validation_resend')] mixed $resendLimiter,
    ): JsonResponse {
        $ip = $request->getClientIp() ?? 'unknown';
        $limit = $resendLimiter->create($ip)->consume(1);

        if (!$limit->isAccepted()) {
            return $this->json(
                ['message' => 'Trop de demandes de renvoi depuis cette adresse IP. Réessayez plus tard.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $email = is_string($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse email invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $authService->resendValidationEmail($email);

        return $this->json(
            ['message' => 'Si ce compte existe et est en attente, un nouvel email de validation a été envoyé.'],
            Response::HTTP_ACCEPTED
        );
    }

    #[Route('/auth/login', name: 'app_login', methods: ['POST'])]
    public function login(Request $request, AuthService $authService): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $tokens = $authService->login($data);
        } catch (LoginThrottledException) {
            return $this->json(
                ['message' => AuthService::LOGIN_ERROR_MESSAGE],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        } catch (LoginFailedException) {
            return $this->json(
                ['message' => AuthService::LOGIN_ERROR_MESSAGE],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $response = $this->json(['token' => $tokens['token']]);
        $response->headers->setCookie(Cookie::create(
            'refresh_token',
            $tokens['refreshToken'],
            time() + AuthService::REFRESH_TOKEN_TTL_SECONDS,
            '/',
            null,
            true,
            true,
            false,
            Cookie::SAMESITE_STRICT
        ));

        return $response;
    }
}