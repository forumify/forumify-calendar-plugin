<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application\Api;

use PluginTests\Tests\Factories\CalendarFactory;
use Symfony\Component\HttpClient\Exception\ClientException;
use Tests\Tests\Application\Api\ApiTestCase;
use Tests\Tests\Application\Api\Crud\DeleteTestTrait;
use Tests\Tests\Application\Api\Crud\GetCollectionTestTrait;
use Tests\Tests\Application\Api\Crud\GetTestTrait;
use Tests\Tests\Application\Api\Crud\PatchTestTrait;
use Tests\Tests\Application\Api\Crud\PostTestTrait;
use Tests\Tests\Factories\OAuth\OAuthClientFactory;

class CalendarApiTest extends ApiTestCase
{
    use GetTestTrait;
    use GetCollectionTestTrait;
    use PostTestTrait;
    use PatchTestTrait;
    use DeleteTestTrait;

    protected static function factory(): string
    {
        return CalendarFactory::class;
    }

    protected static function endpoint(): string
    {
        return '/api/calendar/calendars';
    }

    protected function getPostBody(): array
    {
        return [
            'title' => 'Test Calendar',
            'color' => '#ff8800',
        ];
    }

    protected function getPatchBody(): array
    {
        return ['title' => 'Updated Test Calendar'];
    }

    public function testGetNotAdmin(): void
    {
        $this->oauthClient = OAuthClientFactory::createOne();

        $calendar = CalendarFactory::createOne();

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->get(self::endpoint() . '/' . $calendar->getId());
    }

    public function testGetCollectionNotAdmin(): void
    {
        $this->oauthClient = OAuthClientFactory::createOne();

        CalendarFactory::createMany(3);

        $response = $this->getCollection(self::endpoint());

        self::assertEmpty($response);
    }

    public function testPostNotAdmin(): void
    {
        $this->oauthClient = OAuthClientFactory::createOne();

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->post(self::endpoint(), ['json' => $this->getPostBody()]);
    }
}
