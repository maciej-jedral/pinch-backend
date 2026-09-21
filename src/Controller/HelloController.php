<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class HelloController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[Route('/hello', name: 'app_hello', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $databaseConnected = 1 === (int) $this->connection->fetchOne('SELECT 1');
        } catch (DBALException) {
            $databaseConnected = false;
        }

        return new JsonResponse([
            'message' => 'Hello from Symfony',
            'database' => $databaseConnected ? 'connected' : 'unreachable',
        ]);
    }
}
