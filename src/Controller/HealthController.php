<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(Connection $connection, LoggerInterface $logger): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (DBALException $exception) {
            $logger->error('Application health check failed.', ['exception' => $exception]);

            return new JsonResponse(
                ['status' => 'unavailable'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
