<?php

namespace Omnilab\Launches;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The Space Devs' Launch Library 2: orbital launches, past and planned, by
 * id, text or period - no key needed (15 calls an hour; a supporter's key
 * raises it).
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     api_key: '%env(LL2_API_KEY)%'    # optional: a key from The Space Devs, sent as "Authorization: Token ..."
 *     throttle: 0                      # seconds between two calls
 *     base_uri: 'https://ll.thespacedevs.com/2.3.0/'   # or https://lldev.thespacedevs.com/2.3.0/ (stale data, no limit) to develop
 */
final class LaunchesSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'launches',
            'omnilab.required_options' => [],
            'api_key' => null,
            'throttle' => 0.0,
            'base_uri' => LaunchesSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        $key = $c->get('api_key');

        return new LaunchesSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', LaunchesSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)] + (null === $key || '' === $key ? [] : ['Authorization' => 'Token '.$key]),
            (float) $c->get('throttle'),
        );
    }
}
