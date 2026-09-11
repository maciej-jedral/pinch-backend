<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HelloControllerTest extends WebTestCase
{
    public function testHelloEndpointReturnsOkAndConfirmsDatabaseConnectivity(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/hello');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"message":"Hello from Symfony","database":"connected"}',
            (string) $client->getResponse()->getContent(),
        );
    }
}
