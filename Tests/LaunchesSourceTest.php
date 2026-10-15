<?php

namespace Omnilab\Launches\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\RateLimitedException;
use Omnilab\Launches\LaunchesSource;
use Omnilab\Launches\LaunchesSourceFactory;
use Omnilab\Model\BBox;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Point;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\OccurrenceQuery;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from ll.thespacedevs.com/2.3.0 on 2026-10-16: the
 * launches of 1 to 14 October 2026 in two pages of five (nine), Crew-13 by
 * its id, the search for "Artemis" (eight, the first three), and an id LL2
 * does not have (404).
 */
final class LaunchesSourceTest extends TestCase
{
    /** @var list<array<string, string>> the calls: path, then the query */
    private array $calls = [];

    private function source(?MockResponse $response = null, array $options = []): LaunchesSource
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = ['path' => $path, 'authorization' => $options['normalized_headers']['authorization'][0] ?? ''] + $query;
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                '/2.3.0/launches/18441371-8b2e-457c-afb5-1ec1b11ab630/' === $path => 'launch-crew-13.json',
                '/2.3.0/launches/' === $path && isset($query['search']) => 'search-artemis.json',
                '/2.3.0/launches/' === $path => isset($query['offset']) ? 'occurrences-2.json' : 'occurrences-1.json',
                default => 'launch-unknown.json',
            };

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture), ['http_code' => 'launch-unknown.json' === $fixture ? 404 : 200]);
        });
        $source = (new LaunchesSourceFactory($http))->create($options);
        self::assertInstanceOf(LaunchesSource::class, $source);

        return $source;
    }

    public function testCrew13ByItsId(): void
    {
        $launch = $this->source()->entity('18441371-8b2e-457c-afb5-1ec1b11ab630');

        self::assertSame('/2.3.0/launches/18441371-8b2e-457c-afb5-1ec1b11ab630/', $this->calls[0]['path']);
        self::assertSame('normal', $this->calls[0]['mode']);
        self::assertSame('launchlibrary:18441371-8b2e-457c-afb5-1ec1b11ab630', $launch->id->key());
        self::assertSame('Falcon 9 Block 5 | Crew-13', $launch->label);
        self::assertSame('launch', $launch->kind);
        self::assertSame(Domain::OTHER, $launch->domain);
        self::assertSame('2026-10-01T15:10:06+00:00', $launch->time->format(\DATE_ATOM));
        self::assertEquals(new Point(28.56194122, -80.57735736), $launch->place, 'its pad, SLC-40');
        self::assertSame('Launch Successful', $launch->value('status'));
        self::assertSame('Second', $launch->value('net_precision'));
        self::assertEquals(new Quantity(60, '%'), $launch->value('probability'));
        self::assertSame('SpaceX', $launch->value('provider'));
        self::assertSame('Falcon 9 Block 5', $launch->value('rocket'));
        self::assertSame('Human Exploration', $launch->value('mission_type'));
        self::assertSame('Low Earth Orbit', $launch->value('orbit'));
        self::assertSame('Space Launch Complex 40', $launch->value('pad'));
        self::assertSame('US', $launch->value('country'));
        self::assertSame('https://ll.thespacedevs.com/2.3.0/launches/18441371-8b2e-457c-afb5-1ec1b11ab630/', $launch->url);
        self::assertSame($launch->label, $this->source()->entity(Identifier::of(Scheme::LAUNCH_LIBRARY, '18441371-8B2E-457C-AFB5-1EC1B11AB630'))?->label);
    }

    public function testUnknownAndRefused(): void
    {
        self::assertNull($this->source()->entity('launch:00000000-0000-4000-8000-000000000000'));
        foreach (['wikidata:Q1', 'Crew-13', Identifier::of(Scheme::DONKI, '2026-10-08T15:29:00-FLR-001')] as $id) {
            try {
                $this->source()->entity($id);
                self::fail(\sprintf('%s refused', $id));
            } catch (NotSupportedException) {
            }
        }
        self::assertCount(1, $this->calls);
    }

    public function testTheLaunchesOfTwoWeeksAsOccurrences(): void
    {
        $source = $this->source();
        $first = $source->occurrences(new OccurrenceQuery(null, new \DateTimeImmutable('2026-10-01', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-10-14', new \DateTimeZone('UTC')), limit: 5));

        self::assertSame(['2026-10-01T00:00:00Z', '2026-10-14T23:59:59Z', 'net', '5'], [$this->calls[0]['net__gte'], $this->calls[0]['net__lte'], $this->calls[0]['ordering'], $this->calls[0]['limit']], 'an end at midnight is its whole day');
        self::assertSame(9, $first->total);
        self::assertSame('5', $first->next);
        self::assertCount(5, $first);
        foreach ($first as $launch) {
            self::assertInstanceOf(\DateTimeImmutable::class, $launch->time);
            self::assertInstanceOf(Point::class, $launch->place);
        }
        self::assertSame('Nuri | NeonSat-2 to 6', $first->items[3]->label);
        self::assertSame('Naro Space Center, South Korea', $first->items[3]->value('location'));

        $second = $source->occurrences(new OccurrenceQuery(null, new \DateTimeImmutable('2026-10-01', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-10-14', new \DateTimeZone('UTC')), limit: 5, cursor: $first->next));
        self::assertSame('5', $this->calls[1]['offset']);
        self::assertCount(4, $second);
        self::assertNull($second->next);
        self::assertSame('Zhuque-2E Block 2 | 10 satellites', $second->items[3]->label);
    }

    public function testFiltersAndSorts(): void
    {
        $this->source()->occurrences(new OccurrenceQuery(filters: ['provider' => 'SpaceX', 'rocket' => 'Falcon 9', 'crewed' => true, 'location' => [12, 27], 'orbit' => 'Low Earth'], sort: OccurrenceQuery::NEWEST));

        $call = $this->calls[0];
        self::assertSame(['SpaceX', 'Falcon 9', 'true', '12,27', 'Low Earth', '-net'], [$call['lsp__name'], $call['rocket__configuration__full_name__icontains'], $call['is_crewed'], $call['location__ids'], $call['mission__orbit__name__icontains'], $call['ordering']]);
        self::assertArrayNotHasKey('net__gte', $call);
    }

    public function testWhatIsRefusedBeforeAnyCall(): void
    {
        foreach ([
            new OccurrenceQuery(new BBox(-81, 28, -80, 29)),
            new OccurrenceQuery(new Point(28.5, -80.6), radius: 50),
            new OccurrenceQuery(filters: [OccurrenceQuery::COUNTRY => 'US']),
            new OccurrenceQuery(sort: 'magnitude'),
        ] as $query) {
            try {
                $this->source()->occurrences($query);
                self::fail('refused');
            } catch (NotSupportedException) {
            }
        }
        self::assertSame([], $this->calls);
    }

    public function testTheSearch(): void
    {
        $page = $this->source()->search(new Query('Artemis', limit: 3));

        self::assertSame(['Artemis', '3'], [$this->calls[0]['search'], $this->calls[0]['limit']]);
        self::assertSame(8, $page->total);
        self::assertSame('3', $page->next);
        self::assertSame('SLS Block 1 | Artemis II', $page->items[2]->label);
        self::assertSame('2026-04-01', $page->items[2]->time->format('Y-m-d'));
        self::assertCount(0, $this->source()->search(new Query('Artemis', Domain::ASTRONOMY)));
    }

    public function testAKeyAndTheLimit(): void
    {
        $this->source(null, ['api_key' => 'abc'])->entity('18441371-8b2e-457c-afb5-1ec1b11ab630');
        self::assertSame('Authorization: Token abc', $this->calls[0]['authorization']);

        $factory = new LaunchesSourceFactory(new MockHttpClient());
        self::assertSame('launches', $factory->getName());
        self::assertSame([Capability::ENTITY, Capability::SEARCH, Capability::OCCURRENCES], $factory->create()->capabilities());

        $this->expectException(RateLimitedException::class);
        $this->source(new MockResponse('{"detail":"Request was throttled. Expected available in 3540 seconds."}', ['http_code' => 429, 'response_headers' => ['Retry-After' => '3540']]))->search(new Query('Crew'));
    }
}
