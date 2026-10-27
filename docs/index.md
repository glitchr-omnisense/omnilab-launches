# omnilab/launches

The Space Devs' Launch Library 2 (`https://ll.thespacedevs.com/2.3.0/`) for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): orbital launches, past and
planned, by id or text - rocket, provider, mission, orbit, pad, status - as entities, and the
launches of a period as occurrences at their pads. No key (15 calls an hour).

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

## Credentials

None needed: Launch Library 2 answers 15 calls an hour without a key (a 429 beyond). A higher
limit comes with a key The Space Devs give their supporters (on Patreon), given as `api_key` and
sent as `Authorization: Token ...`. To develop, The Space Devs ask that their development server
be used: `base_uri: https://lldev.thespacedevs.com/2.3.0/` (stale, limited data, no rate limit).

## Options

The factory's options, with the defaults it applies (`LaunchesSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `api_key` | `?string` | null | no | a supporter's key from The Space Devs, sent as `Authorization: Token ...`; null: no header, 15 calls an hour |
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls; the limit is counted an hour (15 without a key): the caller keeps to it |
| `base_uri` | `string` | `https://ll.thespacedevs.com/2.3.0/` | no | LL2 2.3.0: the live server, or the development one (`https://lldev.thespacedevs.com/2.3.0/`) |

Per call, `occurrences()` reads the filters `provider`, `rocket`, `location`, `crewed`, `status`
and `orbit` ([below](#which-api)); any other key, the shared ones (`country`, `kind`...) included,
is refused before any call.

## Capabilities

`capabilities()` answers `entity`, `search`, `occurrences`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | a launch's UUID (`launch:`, `launchlibrary:`, its API address); never read by its shape alone (OBIS's records are UUIDs too): a bare UUID is read as LL2's by this source |
| `search` | yes | LL2's `search` over name, mission, rocket and provider, paged by offset, `total` LL2's count; a domain other than `other` answers an empty page |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | yes | the launches of a period, each at its pad, oldest first by default (`newest` too), 100 a page at most; no place: LL2 filters by no box nor circle |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no | none: LL2 gives Wikipedia links only |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no | a place is refused before any call: narrow by `location` (LL2's launch site ids) |
| `station` | no |  |
| `cloud_cover` | no |  |

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

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Launches\LaunchesSourceFactory;
use Omnilab\Source\OccurrenceQuery;
use Symfony\Component\HttpClient\HttpClient;

$launches = (new LaunchesSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);   // 15 calls an hour without a key

$page = $launches->occurrences(new OccurrenceQuery(null, new DateTimeImmutable('2026-10-20'), new DateTimeImmutable('2026-10-26'), limit: 4));
printf("%d launches from 2026-10-20 to 10-26; the first %d:\n", $page->total, count($page));
foreach ($page as $launch) {
    printf("  %s UTC  %-45s %s, %s\n", $launch->time->format('Y-m-d H:i'), $launch->label, $launch->value('status'), $launch->value('location'));
}
```

```
7 launches from 2026-10-20 to 10-26; the first 4:
  2026-10-20 11:04 UTC  Falcon 9 Block 5 | Bandwagon 5 (Dedicated Mid-Inclination Rideshare) Launch Successful, Cape Canaveral SFS, FL, USA
  2026-10-21 03:39 UTC  Ceres-1S | Xiguang-4 01                       Launch Successful, Haiyang Oriental Spaceport
  2026-10-23 07:11 UTC  Electron | We're Not Hawking Around (3x HawkEye 360) Launch Successful, Rocket Lab Launch Complex 1, Mahia Peninsula, New Zealand
  2026-10-23 10:15 UTC  Long March 6A | Yaogan 50-03                  Launch Successful, Taiyuan Satellite Launch Center, People's Republic of China
```

(as answered on 2026-10-27: one call). SpaceX's crewed launches would be
`[... filters: ['provider' => 'SpaceX', 'crewed' => true]]`.

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        launches: { factory: launches, options: { mailto: '%env(OMNILAB_MAILTO)%', api_key: '%env(default::LL2_API_KEY)%' } }
```

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call (`NotSupportedException`): an identifier that is no launch's, a place in an
occurrence query, a filter key not read, a sort other than `newest` and `oldest`. Past 15 calls an
hour without a key LL2 answers 429: a `RateLimitedException`, with `retryAfter` when LL2 says. An
unknown id is LL2's 404, null.

## Verified, and not

Against the real service on 2026-10-15 and 2026-10-16: Crew-13 by its id (4.1 s), the launches of
1 to 14 October 2026 (nine, in two pages of five), SpaceX's crewed launches from 1 September 2026
(one), the searches for `Artemis` (eight) and `Starship` (27), an unknown id (404); the throttle
endpoint (15 an hour). The fixtures in `Tests/Fixtures/` are its answers.

Again on 2026-10-27: the launches of 20 to 26 October 2026 (seven, four a page; the example
above), one call.

Not verified: the 429 met (the fixture is written after LL2's documented answer; the run stayed
under the limit); a key (none was obtained); every filter's live effect beyond `provider` and
`crewed`.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by The Space Devs. Launch Library 2 is free within its rate limit; The Space Devs ask
that development use their development server and that Launch Library 2 be credited as the
source, each image carrying its own credit and licence, as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-launches#terms-of-use) says. Whoever
runs the package is responsible for complying with them and for any use made of what it returns:
see the README's [Responsibility](https://github.com/glitchr-omnisense/omnilab-launches#responsibility)
section.
