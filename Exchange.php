<?php
/**
 * Created by PhpStorm.
 * User: Faizan Akram <hello@faizanakram.me>
 * Date: 1/6/18
 * Time: 6:36 PM
 */

namespace Qbil\OpenExchangeRates;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Exchange
 */
class Exchange
{

    /** @var Client */
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
        $this->client = new Client([
            'base_uri' => 'https://openexchangerates.org/api/',
        ]);

        $this->options['app_id'] = $appId;

        if ($baseCurrency) {
            $this->options['base'] = $baseCurrency;
        }
    }

    /**
     * @param array{symbols?: string, base?: string, show_alternative?: bool|int, ...} $args
     * @return array<string, float|int> rates keyed by currency code
     * @throws GuzzleException
     */
    public function latest(array $args = [])
    {
        return $this->fetch('latest.json', ['query' => array_merge($this->options, $args)]);
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
        return $this->fetch("historical/{$args['date']}.json", ['query' => array_merge($this->options, $args)]);
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
        return $this->fetch('time-series.json', ['query' => array_merge($this->options, $args)]);
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
        return $this->fetch("convert/{$args['amount']}/{$args['from']}/{$args['to']}", ['query' => array_merge($this->options, $args)], 'response');
    }

    /**
     * @param array{show_alternative?: bool|int, show_inactive?: bool|int, ...} $args
     * @return array<string, string> currency names keyed by currency code
     * @throws GuzzleException
     */
    public function currencies(array $args = [])
    {
        return json_decode(
            $this
                ->client
                ->request('GET', 'currencies.json', ['query' => array_merge($this->options, $args)])
                ->getBody()
                ->getContents(),
            true
        );
    }

    /**
     * @param string $route
     * @param array<string, mixed> $options
     * @param string $responseKey
     * @return mixed
     * @throws GuzzleException
     */
    private function fetch($route, array $options, $responseKey = 'rates')
    {
        $response = json_decode(
            $this
                ->client
                ->request('GET', $route, $options)
                ->getBody()
                ->getContents(),
            true
        );
        return $response[$responseKey];
    }
}
