<?php
/**
 * Created by PhpStorm.
 * User: Faizan Akram <hello@faizanakram.me>
 * Date: 1/6/18
 * Time: 6:36 PM
 */

namespace Qbil\OpenExchangeRates;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * Class Exchange
 */
class Exchange
{
    private const BASE_URI = 'https://openexchangerates.org/api/';

    /** @var ClientInterface */
    private $client;

    /**
     * @var array<string, string>
     */
    private $options = [];

    /**
     * Exchange constructor.
     * @param string $appId
     * @param string|null $baseCurrency
     */
    public function __construct($appId, $baseCurrency = null)
    {
        $this->client = new Client();

        $this->options['app_id'] = $appId;

        if ($baseCurrency) {
            $this->options['base'] = $baseCurrency;
        }
    }

    /**
     * Returns a copy that sends requests through the given PSR-18 client instead of Guzzle.
     *
     * A wither rather than a constructor argument, so Symfony autowiring does not
     * silently swap the client of existing service definitions.
     *
     * @return static
     */
    public function withHttpClient(ClientInterface $client): self
    {
        $exchange = clone $this;
        $exchange->client = $client;

        return $exchange;
    }

    /**
     * @param array{symbols?: string, base?: string, show_alternative?: bool|int, ...} $args
     * @return array<string, float|int> rates keyed by currency code
     * @throws GuzzleException
     */
    public function latest(array $args = [])
    {
        return $this->fetch('latest.json', $args);
    }

    /**
     * @param array{date: string, symbols?: string, base?: string, show_alternative?: bool|int, ...} $args date as YYYY-MM-DD
     * @return array<string, float|int> rates keyed by currency code
     * @throws GuzzleException
     */
    public function historical(array $args)
    {
        if (empty($args['date'])) {
            throw new \InvalidArgumentException('Required parameter `date` is missing');
        }
        return $this->fetch("historical/{$args['date']}.json", $args);
    }

    /**
     * @param array{start: string, end: string, symbols?: string, base?: string, show_alternative?: bool|int, ...} $args dates as YYYY-MM-DD
     * @return array<string, array<string, float|int>> rates keyed by date, then by currency code
     * @throws GuzzleException
     */
    public function between(array $args)
    {
        if (empty($args['start'])) {
            throw new \InvalidArgumentException('Required parameter `start` is missing');
        }
        if (empty($args['end'])) {
            throw new \InvalidArgumentException('Required parameter `end` is missing');
        }
        return $this->fetch('time-series.json', $args);
    }

    /**
     * @param array{amount: float|int|string, from: string, to: string, ...} $args from/to as currency codes
     * @return float|int the converted amount
     * @throws GuzzleException
     */
    public function convert(array $args)
    {
        if (empty($args['amount'])) {
            throw new \InvalidArgumentException('Required parameter `amount` is missing');
        }
        if (empty($args['from'])) {
            throw new \InvalidArgumentException('Required parameter `from` is missing');
        }
        if (empty($args['to'])) {
            throw new \InvalidArgumentException('Required parameter `to` is missing');
        }
        return $this->fetch("convert/{$args['amount']}/{$args['from']}/{$args['to']}", $args, 'response');
    }

    /**
     * @param array{show_alternative?: bool|int, show_inactive?: bool|int, ...} $args
     * @return array<string, string> currency names keyed by currency code
     * @throws GuzzleException
     */
    public function currencies(array $args = [])
    {
        return $this->request('currencies.json', $args);
    }

    /**
     * @param array<string, mixed> $args
     * @return mixed
     * @throws GuzzleException
     */
    private function fetch(string $route, array $args, string $responseKey = 'rates')
    {
        return $this->request($route, $args)[$responseKey];
    }

    /**
     * Failures are always reported as GuzzleException, whichever PSR-18 client is used.
     *
     * @param array<string, mixed> $args
     * @return mixed the decoded JSON body
     * @throws GuzzleException
     */
    private function request(string $route, array $args)
    {
        $query = http_build_query(array_merge($this->options, $args), '', '&', PHP_QUERY_RFC3986);
        $request = new Request('GET', self::BASE_URI.$route.'?'.$query);

        try {
            $response = $this->client->sendRequest($request);
        } catch (GuzzleException $e) {
            throw $e;
        } catch (NetworkExceptionInterface $e) {
            throw new ConnectException($e->getMessage(), $request, $e);
        } catch (ClientExceptionInterface $e) {
            throw new RequestException($e->getMessage(), $request, null, $e);
        }

        if ($response->getStatusCode() >= 400) {
            throw RequestException::create($request, $response);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
