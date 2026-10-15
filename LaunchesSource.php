<?php

namespace Omnilab\Launches;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Point;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\OccurrenceQuery;
use Omnilab\Source\Page;
use Omnilab\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Space Devs' Launch Library 2 (ll.thespacedevs.com/2.3.0): orbital
 * launches, past and planned - a launch by its id (its rocket, provider,
 * mission and orbit, pad and its place, date and its precision, window,
 * status, probability, the failure's reason), a search by text, and the
 * launches of a period as occurrences: each has its time (NET, "no earlier
 * than") and its place (the pad's latitude and longitude).
 *
 * No key: 15 calls an hour for an unauthenticated caller (429 beyond); a
 * key from The Space Devs' supporters raises it ("api_key", sent as
 * "Authorization: Token ..."). LL2 filters by no box nor circle: an
 * occurrence query names no place (or is refused), and narrows by
 * provider, rocket, location or crew instead.
 */
final class LaunchesSource extends HttpSource
{
    public const BASE_URI = 'https://ll.thespacedevs.com/2.3.0/';

    /** The most launches a page holds. */
    public const MAX_PER_PAGE = 100;

    /** The filters read: key => LL2's parameter. */
    public const FILTERS = [
        'provider' => 'lsp__name',
        'rocket' => 'rocket__configuration__full_name__icontains',
        'location' => 'location__ids',
        'crewed' => 'is_crewed',
        'status' => 'status__ids',
        'orbit' => 'mission__orbit__name__icontains',
    ];

    public function __construct(
        HttpClientInterface $http,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'launches';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH, Capability::OCCURRENCES];
    }

    /** A launch by its id (a UUID: launchlibrary:..., or its API address); null when LL2 has none. */
    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = self::identify($id, Scheme::LAUNCH_LIBRARY);
        if (null === $identifier && \is_string($id)) {
            $identifier = Identifier::tryOf(Scheme::LAUNCH_LIBRARY, $id);
        }
        if (null === $identifier) {
            throw NotSupportedException::identifier('launches', (string) $id);
        }
        $data = $this->getJson('launches/'.$identifier->value.'/', ['mode' => 'normal']);

        return null === $data ? null : $this->toEntity($data);
    }

    /** Launches whose name, mission, rocket or provider holds the text (LL2's search), the earliest first, by offset. */
    public function search(Query $query): Page
    {
        if (null !== $query->domain && Domain::OTHER !== $query->domain) {
            return new Page();
        }

        return $this->page(['search' => trim($query->text)], $query->limit, $query->cursor);
    }

    /**
     * The launches of a period (no place: anywhere; LL2 filters by no box
     * nor circle, so a place is refused), by NET, the source's order being
     * the earliest first; filters: provider (LL2's name: SpaceX), rocket
     * (a part of its full name: Falcon 9), location (LL2's location ids),
     * crewed (true/false), status (LL2's status ids: 3 success, 4
     * failure...), orbit (a part of its name: Low Earth Orbit). Sorts
     * newest and oldest.
     */
    public function occurrences(OccurrenceQuery $query): Page
    {
        if (null !== $query->place) {
            throw NotSupportedException::operation('launches', 'list launches at a place: LL2 filters by no box nor circle (filter by "location", LL2\'s ids, instead)');
        }
        $filters = $this->filters($query, ...array_keys(self::FILTERS));
        $ordering = match ($query->sort) {
            null, OccurrenceQuery::OLDEST => 'net',
            OccurrenceQuery::NEWEST => '-net',
            default => throw NotSupportedException::operation('launches', \sprintf('sort launches by "%s" (it sorts newest, oldest)', $query->sort)),
        };
        $parameters = ['ordering' => $ordering];
        if (null !== $query->from) {
            $parameters['net__gte'] = self::utc($query->from)->format('Y-m-d\TH:i:s\Z');
        }
        if (null !== $query->to) {
            $to = self::utc($query->to);
            $parameters['net__lte'] = ('00:00:00' === $to->format('H:i:s') ? $to->modify('+1 day -1 second') : $to)->format('Y-m-d\TH:i:s\Z');
        }
        foreach ($filters as $key => $value) {
            $parameters[self::FILTERS[$key]] = match (true) {
                \is_bool($value) => $value ? 'true' : 'false',
                \is_array($value) => implode(',', $value),
                default => (string) $value,
            };
        }

        return $this->page($parameters, $query->limit, $query->cursor);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return Page<Entity>
     */
    private function page(array $parameters, int $limit, ?string $cursor): Page
    {
        $limit = max(1, min(self::MAX_PER_PAGE, $limit));
        $offset = max(0, (int) $cursor);
        $data = $this->getJson('launches/', ['mode' => 'normal', 'limit' => $limit, 'offset' => $offset > 0 ? $offset : null] + $parameters) ?? [];
        $items = array_values(array_map(fn (array $launch) => $this->toEntity($launch), array_filter($data['results'] ?? [], 'is_array')));
        $total = isset($data['count']) ? (int) $data['count'] : null;

        return new Page($items, null !== ($data['next'] ?? null) ? (string) ($offset + $limit) : null, $total);
    }

    /** @param array<string, mixed> $launch */
    private function toEntity(array $launch): Entity
    {
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value) {
                $properties[] = new Property($key, $label, $value, 'launches');
            }
        };
        $add('status', 'Status', self::text($launch['status']['name'] ?? null));
        $add('status_code', 'Status (abbreviation)', self::text($launch['status']['abbrev'] ?? null));
        $add('net', 'No earlier than (UTC)', self::time($launch['net'] ?? null)?->format('Y-m-d H:i:s'));
        $add('net_precision', 'Precision of the date', self::text($launch['net_precision']['name'] ?? null));
        $add('window_start', 'Window opens (UTC)', self::time($launch['window_start'] ?? null)?->format('Y-m-d H:i:s'));
        $add('window_end', 'Window closes (UTC)', self::time($launch['window_end'] ?? null)?->format('Y-m-d H:i:s'));
        $add('probability', 'Probability of launch (weather)', is_numeric($launch['probability'] ?? null) ? new Quantity((int) $launch['probability'], '%') : null);
        $add('failure_reason', 'Failure', self::text($launch['failreason'] ?? null));
        $add('provider', 'Launch service provider', self::text($launch['launch_service_provider']['name'] ?? null));
        $add('provider_type', 'Provider type', self::text($launch['launch_service_provider']['type']['name'] ?? null));
        $add('rocket', 'Rocket', self::text($launch['rocket']['configuration']['full_name'] ?? $launch['rocket']['configuration']['name'] ?? null));
        $mission = \is_array($launch['mission'] ?? null) ? $launch['mission'] : [];
        $add('mission', 'Mission', self::text($mission['name'] ?? null));
        $add('mission_type', 'Mission type', self::text($mission['type'] ?? null));
        $add('orbit', 'Orbit', self::text($mission['orbit']['name'] ?? null));
        $pad = \is_array($launch['pad'] ?? null) ? $launch['pad'] : [];
        $add('pad', 'Pad', self::text($pad['name'] ?? null));
        $add('location', 'Launch site', self::text($pad['location']['name'] ?? null));
        $add('location_id', 'Launch site (LL2 id)', is_numeric($pad['location']['id'] ?? null) ? (int) $pad['location']['id'] : null);
        $add('country', 'Country', self::text($pad['country']['alpha_2_code'] ?? $pad['location']['country']['alpha_2_code'] ?? null));
        $add('webcast_live', 'Webcast live', \is_bool($launch['webcast_live'] ?? null) ? $launch['webcast_live'] : null);
        $add('orbital_launch_attempt_count_year', 'Orbital launch attempt of the year (worldwide)', is_numeric($launch['orbital_launch_attempt_count_year'] ?? null) ? (int) $launch['orbital_launch_attempt_count_year'] : null);

        $place = null;
        if (is_numeric($pad['latitude'] ?? null) && is_numeric($pad['longitude'] ?? null)) {
            $place = new Point((float) $pad['latitude'], (float) $pad['longitude']);
        }
        $id = Identifier::of(Scheme::LAUNCH_LIBRARY, (string) $launch['id']);
        $image = $launch['image']['image_url'] ?? null;

        return new Entity(
            id: $id,
            label: (string) (self::text($launch['name'] ?? null) ?? $id->value),
            domain: Domain::OTHER,
            kind: 'launch',
            description: self::text($mission['description'] ?? null),
            properties: $properties,
            url: $id->url(),
            image: \is_string($image) ? $image : null,
            time: self::time($launch['net'] ?? null)?->setTimezone(new \DateTimeZone('UTC')),
            place: $place,
            source: 'launches',
        );
    }

    private static function utc(\DateTimeInterface $time): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($time)->setTimezone(new \DateTimeZone('UTC'));
    }
}
