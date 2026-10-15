# omnilab/launches

## Installation

```sh
composer require omnilab/launches
```

```php
use Omnilab\Launches\LaunchesSourceFactory;

$launches = (new LaunchesSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional, in the User-Agent
    'api_key' => null,                 // optional: a key from The Space Devs ("Authorization: Token ...")
    'base_uri' => 'https://lldev.thespacedevs.com/2.3.0/',   // to develop: stale data, no rate limit
]);
```

With the Symfony bundle, the factory is registered as `launches` when the bundle knows it;
otherwise register `LaunchesSourceFactory` as a service (it is autoconfigured as a
`SourceFactoryInterface`).

## Which API

Launch Library 2, version 2.3.0 (`https://ll.thespacedevs.com/2.3.0/`, its OpenAPI at `schema/`):
`launches/<id>/` and `launches/?...`, always in `mode=normal` (the pad with its coordinates, the
rocket's configuration, the mission and its orbit; `detailed` adds the provider's and the rocket's
statistics, 100 kB a launch). 15 calls an hour without a key (`api-throttle/` tells the use).

| Method | Reads | Calls |
|---|---|---|
| `entity()` | a launch's UUID, `launch:...`, `launchlibrary:...`, its API address | `launches/<id>/?mode=normal`; 404 is `null` |
| `search()` | `Query::$text` (LL2's `search`: name, mission, rocket, provider), a domain other than `other` answers nothing | `launches/?search=&limit=&offset=` |
| `occurrences()` | a period (both ends included, an end at midnight its whole day; open ends allowed), filters, sort `oldest` (default) or `newest`, `limit` (100 at most), cursor | `launches/?net__gte=&net__lte=&ordering=net\|-net&...` |

**Occurrences**: each launch has its `time` (NET, UTC) and its `place` (the pad's latitude and
longitude). LL2 filters by no box nor circle, so a query that names a place is refused before
any call (no `area` capability, and no answer filtered by hand: the pages would lie); narrow by
launch site with `location` instead. Filters (each refused before any call when unknown):

| Key | LL2 | Value |
|---|---|---|
| `provider` | `lsp__name` | the provider's name: `SpaceX`, `China Aerospace Science and Technology Corporation` |
| `rocket` | `rocket__configuration__full_name__icontains` | a part of the rocket's full name: `Falcon 9` |
| `location` | `location__ids` | LL2's launch site ids (12 Cape Canaveral SFS): one or a list |
| `crewed` | `is_crewed` | true, false |
| `status` | `status__ids` | LL2's status ids (3 success, 4 failure, 7 partial failure...): one or a list |
| `orbit` | `mission__orbit__name__icontains` | a part of the orbit's name: `Low Earth`, `Sun-Synchronous` |

Paging by offset (`Page::$next`), `total` LL2's count. No shared key (`country`, `kind`...) is read:
LL2's launch list filters by no country.

## What an entity holds

`id` in the `launchlibrary` scheme, `label` LL2's name (`Falcon 9 Block 5 | Crew-13`), `domain`
other (the core has no spaceflight domain), `kind` `launch`, `description` the mission's, `url` its
API record (LL2 has no page of its own), `image` LL2's picture (its credit and licence in LL2),
`time`, `place`, and: `status`, `status_code`, `net`, `net_precision` (Second, Minute, Hour, Day,
Month, Quarter...), `window_start`, `window_end`, `probability` (%, the weather's), `failure_reason`,
`provider`, `provider_type`, `rocket`, `mission`, `mission_type`, `orbit`, `pad`, `location`,
`location_id`, `country` (ISO 3166-1 alpha-2), `webcast_live`, `orbital_launch_attempt_count_year`.
No identifier of another base (LL2 gives Wikipedia links only), no `related`.

## What LL2 does not give here

Events, astronauts, spacecraft, space stations, expeditions, dockings, agencies, launchers and
landings (each an endpoint of LL2), the `detailed` mode's statistics, the timelines, the updates,
the videos, and the Spaceflight News API.

## Verified, and not

Against the real service on 2026-10-15 and 2026-10-16: Crew-13 by its id (4.1 s), the launches of
1 to 14 October 2026 (nine, in two pages of five), SpaceX's crewed launches from 1 September 2026
(one), the searches for `Artemis` (eight) and `Starship` (27), an unknown id (404); the throttle
endpoint (15 an hour). The fixtures in `Tests/Fixtures/` are its answers.

Not verified: the 429 met (the fixture is written after LL2's documented answer; the run stayed
under the limit); a key (none was obtained); every filter's live effect beyond `provider` and
`crewed`.
