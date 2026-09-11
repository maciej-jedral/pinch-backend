<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HelloController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[Route('/api/hello', name: 'app_hello', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseConnected = 1 === (int) $this->connection->fetchOne('SELECT 1');

        return new JsonResponse([
            'message' => 'Hello from Symfony',
            'database' => $databaseConnected ? 'connected' : 'unreachable',
        ]);
    }
}
