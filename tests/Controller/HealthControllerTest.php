<?php

namespace App\Tests\Controller;

use App\Controller\HealthController;
use App\Tests\Doubles\BaseIndisponibleException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class HealthControllerTest extends TestCase
{
    public function testHealthCheckReturnsOkAndVersionWhenDatabaseIsAvailable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createStub(Result::class));

        $response = (new HealthController())($connection, $this->createStub(LoggerInterface::class), 'abc123');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok","version":"abc123"}', $response->getContent());
    }

    public function testHealthCheckReturns503AndLogsErrorWhenDatabaseIsUnavailable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')
            ->willThrowException(new BaseIndisponibleException());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Application health check failed.');

        $response = (new HealthController())($connection, $logger, 'abc123');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('{"status":"unavailable","version":"abc123"}', $response->getContent());
    }
}
