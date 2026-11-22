<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    /**
     * Sonde de sante : l'application repond ET MySQL repond.
     * La reponse indique la version en service (SHA du commit, fixe dans l'image au build)
     * pour verifier apres un deploiement ou un rollback quelle version tourne reellement.
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(
        Connection $connection,
        LoggerInterface $logger,
        #[Autowire(param: 'app.version')] string $version,
    ): JsonResponse {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (DBALException $exception) {
            $logger->error('Application health check failed.', ['exception' => $exception, 'version' => $version]);

            return new JsonResponse(
                ['status' => 'unavailable', 'version' => $version],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new JsonResponse(['status' => 'ok', 'version' => $version]);
    }
}
