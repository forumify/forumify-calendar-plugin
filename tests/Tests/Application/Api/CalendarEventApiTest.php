<?php

declare(strict_types=1);

namespace PluginTests\Tests\Application\Api;

use ApiPlatform\Metadata\IriConverterInterface;
use PluginTests\Tests\Factories\CalendarEventFactory;
use PluginTests\Tests\Factories\CalendarFactory;
use Symfony\Component\HttpClient\Exception\ClientException;
use Tests\Tests\Application\Api\ApiTestCase;
use Tests\Tests\Application\Api\Crud\DeleteTestTrait;
use Tests\Tests\Application\Api\Crud\GetCollectionTestTrait;
use Tests\Tests\Application\Api\Crud\GetTestTrait;
use Tests\Tests\Application\Api\Crud\PatchTestTrait;
use Tests\Tests\Factories\OAuth\OAuthClientFactory;

class CalendarEventApiTest extends ApiTestCase
{
    use GetTestTrait;
    use GetCollectionTestTrait;
    use PatchTestTrait;
    use DeleteTestTrait;

    protected static function factory(): string
    {
        return CalendarEventFactory::class;
    }

    protected static function endpoint(): string
    {
        return '/api/calendar/calendar-events';
    }

    protected function getPatchBody(): array
    {
        return ['title' => 'Updated Test Event'];
    }

    public function testGetCollectionOnCalendar(): void
    {
        $event = CalendarEventFactory::createOne();
        $otherEvent = CalendarEventFactory::createOne();

        $response = $this->getCollection("/api/calendar/{$event->getCalendar()->getId()}/events");

        self::assertCount(1, $response);

        $ids = array_column($response, 'id');
        self::assertContains($event->getId(), $ids);
        self::assertNotContains($otherEvent->getId(), $ids);
    }

    public function testPostOnCalendar(): void
    {
        $calendar = CalendarFactory::createOne();
        $calendarIri = self::getContainer()->get(IriConverterInterface::class)->getIriFromResource($calendar);

        $response = $this->post("/api/calendar/{$calendar->getId()}/events", [
            'json' => [
                'title' => 'Test Event',
                'content' => 'Something is happening',
                'start' => '2026-03-01T18:00:00+00:00',
                'end' => '2026-03-01T20:00:00+00:00',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertIsInt($response['id']);
        self::assertEquals('Test Event', $response['title']);
        self::assertEquals($calendarIri, $response['calendar']);
    }

    public function testPostOnCalendarWithBanner(): void
    {
        $calendar = CalendarFactory::createOne();

        $response = $this->post("/api/calendar/{$calendar->getId()}/events", [
            'json' => [
                'title' => 'Event With Banner',
                'content' => 'Something is happening',
                'start' => '2026-03-01T18:00:00+00:00',
                'newBanner' => [
                    'filename' => 'banner.png',
                    'data' => base64_encode((string)file_get_contents(TEST_DATA_DIR . '/banner.png')),
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('/storage/assets/', $response['banner']);
        self::assertStringEndsWith('banner.png', $response['banner']);
    }

    public function testGetNotAdmin(): void
    {
        $this->oauthClient = OAuthClientFactory::createOne();

        $event = CalendarEventFactory::createOne();

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->get(self::endpoint() . '/' . $event->getId());
    }

    public function testGetCollectionNotAdmin(): void
    {
        $this->oauthClient = OAuthClientFactory::createOne();

        CalendarEventFactory::createMany(3);

        $response = $this->getCollection(self::endpoint());

        self::assertEmpty($response);
    }

    public function testPostOnCalendarNotAdmin(): void
    {
        $calendar = CalendarFactory::createOne();
        $this->oauthClient = OAuthClientFactory::createOne();

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->post("/api/calendar/{$calendar->getId()}/events", [
            'json' => [
                'title' => 'Test Event',
                'content' => 'Something is happening',
                'start' => '2026-03-01T18:00:00+00:00',
            ],
        ]);
    }
}
