<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness probe: proves the app answers HTTP, nothing more. Deliberately
 * touches no database, so it is safe to poll at any interval (a periodic
 * query would keep the Neon free-tier compute from scaling to zero).
 */
#[Route('/api')]
final class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
