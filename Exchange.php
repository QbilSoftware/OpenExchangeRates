<?php
/**
 * Created by PhpStorm.
 * User: Faizan Akram <hello@faizanakram.me>
 * Date: 1/6/18
 * Time: 6:36 PM
 */

namespace Qbil\OpenExchangeRates;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class Exchange
 */
class Exchange
{
    private const string BASE_URI = 'https://openexchangerates.org/api/';

    private readonly HttpClientInterface $client;

    /**
     * @var array<string, string>
     */
    private array $options = [];

    /**
     * Exchange constructor.
     * @param HttpClientInterface|null $client e.g. the framework's `http_client` service; a default client is created when omitted
     */
    public function __construct(string $appId, ?string $baseCurrency = null, ?HttpClientInterface $client = null)
    {
        $this->client = ($client ?? HttpClient::create())->withOptions([
            'base_uri' => self::BASE_URI,
        ]);

        $this->options['app_id'] = $appId;

        if ($baseCurrency) {
            $this->options['base'] = $baseCurrency;
        }
    }

    /**
     * @return array<string, float>
     * @throws ExceptionInterface
     */
    public function latest(array $args = []): array
    {
        return $this->fetch('latest.json', $args);
    }

    /**
     * @return array<string, float>
     * @throws ExceptionInterface
     */
    public function historical(array $args): array
    {
        $this->assertRequired($args, 'date');

        return $this->fetch("historical/{$args['date']}.json", $args);
    }

    /**
     * @return array<string, array<string, float>>
     * @throws ExceptionInterface
     */
    public function between(array $args): array
    {
        $this->assertRequired($args, 'start', 'end');

        return $this->fetch('time-series.json', $args);
    }

    /**
     * @throws ExceptionInterface
     */
    public function convert(array $args): float
    {
        $this->assertRequired($args, 'amount', 'from', 'to');

        return (float) $this->fetch("convert/{$args['amount']}/{$args['from']}/{$args['to']}", $args, 'response');
    }

    /**
     * @return array<string, string>
     * @throws ExceptionInterface
     */
    public function currencies(array $args = []): array
    {
        return $this->request('currencies.json', $args);
    }

    /**
     * @throws ExceptionInterface
     */
    private function fetch(string $route, array $args, string $responseKey = 'rates'): mixed
    {
        return $this->request($route, $args)[$responseKey];
    }

    /**
     * @throws ExceptionInterface
     */
    private function request(string $route, array $args): array
    {
        return $this
            ->client
            ->request('GET', $route, ['query' => array_merge($this->options, $args)])
            ->toArray();
    }

    private function assertRequired(array $args, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (empty($args[$key])) {
                throw new \InvalidArgumentException("Required parameter `{$key}` is missing");
            }
        }
    }
}
