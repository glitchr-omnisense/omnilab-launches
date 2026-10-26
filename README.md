# omnilab/launches

The Space Devs' Launch Library 2 for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab):
orbital launches, past and planned - a launch by its id (its rocket, provider, mission and orbit,
pad and its place, date and its precision, window, status, probability, the failure's reason), a
search by text, and the launches of a period as occurrences, each with its time (NET, "no earlier
than") and its place (the pad), filtered by provider, rocket, launch site, crew, status or orbit.
No key: 15 calls an hour.

```php
$launches = (new LaunchesSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$crew13 = $launches->entity('18441371-8b2e-457c-afb5-1ec1b11ab630');
$crew13->label;                     // Falcon 9 Block 5 | Crew-13
$crew13->time;                      // 2026-10-01 15:10:06 UTC
$crew13->place;                     // 28.56194122,-80.57735736 (SLC-40)
$crew13->value('status');           // Launch Successful
$launches->occurrences(new OccurrenceQuery(null, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-14')));   // nine launches
$launches->occurrences(new OccurrenceQuery(filters: ['provider' => 'SpaceX', 'crewed' => true], sort: OccurrenceQuery::NEWEST));
$launches->search(new Query('Artemis'));
```

```yaml
omnilab:
    sources:
        launches:
            factory: launches
            options:
                mailto: 'support@glitchr.io'     # in the User-Agent
                api_key: '%env(LL2_API_KEY)%'    # optional: a supporter's key from The Space Devs, a higher rate
```

See [docs/](docs/index.md) for what is read, how it maps, and what LL2 does not give here.

## Terms of use

Launch Library 2 is a product of The Space Devs, free to use within its rate limit: 15 calls an
hour without a key (a 429 beyond, a `RateLimitedException` here); a higher limit comes with a key
that The Space Devs give their supporters (on Patreon), which the user obtains and passes as
`api_key`. For development, The Space Devs ask that `https://lldev.thespacedevs.com/2.3.0/` (stale,
limited data, no rate limit) be used: set it as `base_uri`. Credit The Space Devs' Launch Library
2 as the source; each image carries its own credit and licence.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by The Space Devs. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
