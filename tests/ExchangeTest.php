<?php

namespace Qbil\OpenExchangeRates\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Qbil\OpenExchangeRates\Exchange;

class ExchangeTest extends TestCase
{
    private const RATES = ['EUR' => 0.92, 'GBP' => 0.79, 'USD' => 1];

    /** @var array<int, RequestInterface> */
    private $requests = [];

    public function testLatestReturnsRates(): void
    {
        $rates = $this->exchange(['base' => 'USD', 'rates' => self::RATES])->latest();

        self::assertSame(self::RATES, $rates);
        self::assertSame('GET', $this->lastRequest()->getMethod());
        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID&base=USD', $this->lastUrl());
    }

    public function testRequestArgumentsAreSentAsQueryParameters(): void
    {
        $this->exchange(['rates' => self::RATES])->latest(['symbols' => 'EUR,GBP', 'show_alternative' => true]);

        self::assertSame(
            'https://openexchangerates.org/api/latest.json?app_id=APP_ID&base=USD&symbols=EUR%2CGBP&show_alternative=1',
            $this->lastUrl()
        );
    }

    public function testRequestBaseOverridesConstructorBase(): void
    {
        $this->exchange(['rates' => self::RATES], 200, 'USD')->latest(['base' => 'GBP']);

        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID&base=GBP', $this->lastUrl());
    }

    public function testBaseIsOmittedWhenNotConfigured(): void
    {
        $this->exchange(['rates' => self::RATES], 200, null)->latest();

        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID', $this->lastUrl());
    }

    public function testHistoricalReturnsRatesForDate(): void
    {
        $rates = $this->exchange(['rates' => self::RATES])->historical(['date' => '2017-10-01']);

        self::assertSame(self::RATES, $rates);
        self::assertSame(
            'https://openexchangerates.org/api/historical/2017-10-01.json?app_id=APP_ID&base=USD&date=2017-10-01',
            $this->lastUrl()
        );
    }

    public function testBetweenReturnsRatesPerDate(): void
    {
        $series = ['2017-10-01' => self::RATES, '2017-10-02' => self::RATES];

        $rates = $this->exchange(['rates' => $series])->between(['start' => '2017-10-01', 'end' => '2017-10-02']);

        self::assertSame($series, $rates);
        self::assertSame(
            'https://openexchangerates.org/api/time-series.json?app_id=APP_ID&base=USD&start=2017-10-01&end=2017-10-02',
            $this->lastUrl()
        );
    }

    public function testConvertReturnsConvertedAmount(): void
    {
        $amount = $this->exchange(['response' => 25048.86])->convert(['amount' => '19999.95', 'from' => 'GBP', 'to' => 'EUR']);

        self::assertSame(25048.86, $amount);
        self::assertStringStartsWith('https://openexchangerates.org/api/convert/19999.95/GBP/EUR?', $this->lastUrl());
    }

    public function testCurrenciesReturnsWholeResponse(): void
    {
        $currencies = ['EUR' => 'Euro', 'USD' => 'United States Dollar'];

        self::assertSame($currencies, $this->exchange($currencies)->currencies(['show_alternative' => 1]));
        self::assertSame(
            'https://openexchangerates.org/api/currencies.json?app_id=APP_ID&base=USD&show_alternative=1',
            $this->lastUrl()
        );
    }

    /**
     * @dataProvider missingParameterProvider
     * @param array<string, mixed> $args
     */
    #[DataProvider('missingParameterProvider')]
    public function testMissingRequiredParameterThrows(string $method, array $args, string $missing): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::never())->method('sendRequest');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required parameter `{$missing}` is missing");

        (new Exchange('APP_ID'))->withHttpClient($client)->{$method}($args);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function missingParameterProvider(): iterable
    {
        yield 'historical without date' => ['historical', [], 'date'];
        yield 'historical with empty date' => ['historical', ['date' => ''], 'date'];
        yield 'between without start' => ['between', ['end' => '2017-10-02'], 'start'];
        yield 'between without end' => ['between', ['start' => '2017-10-01'], 'end'];
        yield 'convert without amount' => ['convert', ['from' => 'USD', 'to' => 'EUR'], 'amount'];
        yield 'convert without from' => ['convert', ['amount' => 10, 'to' => 'EUR'], 'from'];
        yield 'convert without to' => ['convert', ['amount' => 10, 'from' => 'USD'], 'to'];
    }

    public function testClientErrorThrowsGuzzleClientException(): void
    {
        $exchange = $this->exchange(['error' => true, 'status' => 401, 'message' => 'invalid_app_id'], 401);

        try {
            $exchange->latest();
            self::fail('Expected a ClientException.');
        } catch (ClientException $e) {
            self::assertSame(401, $e->getResponse()->getStatusCode());
            self::assertStringContainsString('401 Unauthorized', $e->getMessage());
        }
    }

    public function testServerErrorThrowsGuzzleServerException(): void
    {
        $this->expectException(ServerException::class);

        $this->exchange([], 503)->currencies();
    }

    public function testGuzzleExceptionsArePassedThrough(): void
    {
        $exception = new ConnectException('Connection timed out', new Request('GET', 'https://openexchangerates.org'));

        try {
            $this->guzzleExchange([$exception])->latest();
            self::fail('Expected a ConnectException.');
        } catch (ConnectException $e) {
            self::assertSame($exception, $e);
        }
    }

    public function testDefaultsToGuzzleClient(): void
    {
        self::assertInstanceOf(Client::class, $this->clientOf(new Exchange('APP_ID')));
    }

    public function testWithHttpClientReturnsCopyAndKeepsOriginal(): void
    {
        $original = new Exchange('APP_ID', 'USD');
        $client = $this->createStub(ClientInterface::class);

        $copy = $original->withHttpClient($client);

        self::assertNotSame($original, $copy);
        self::assertSame($client, $this->clientOf($copy));
        self::assertInstanceOf(Client::class, $this->clientOf($original));
    }

    public function testAcceptsAnyPsr18Client(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(function (RequestInterface $request) {
                return (string) $request->getUri() === 'https://openexchangerates.org/api/latest.json?app_id=APP_ID&symbols=EUR';
            }))
            ->willReturn(new Response(200, [], (string) json_encode(['rates' => ['EUR' => 0.92]])));

        self::assertSame(['EUR' => 0.92], (new Exchange('APP_ID'))->withHttpClient($client)->latest(['symbols' => 'EUR']));
    }

    public function testHttpErrorFromPsr18ClientThrowsGuzzleClientException(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willReturn(new Response(401));

        $this->expectException(ClientException::class);

        (new Exchange('APP_ID'))->withHttpClient($client)->latest();
    }

    public function testNetworkErrorFromPsr18ClientThrowsGuzzleConnectException(): void
    {
        $exception = new class('Connection refused') extends \RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return new Request('GET', 'https://openexchangerates.org');
            }
        };
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willThrowException($exception);

        try {
            (new Exchange('APP_ID'))->withHttpClient($client)->latest();
            self::fail('Expected a ConnectException.');
        } catch (ConnectException $e) {
            self::assertSame('Connection refused', $e->getMessage());
            self::assertSame($exception, $e->getPrevious());
        }
    }

    public function testOtherErrorFromPsr18ClientThrowsGuzzleRequestException(): void
    {
        $exception = new class('Invalid request') extends \RuntimeException implements ClientExceptionInterface {
        };
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willThrowException($exception);

        try {
            (new Exchange('APP_ID'))->withHttpClient($client)->latest();
            self::fail('Expected a RequestException.');
        } catch (RequestException $e) {
            self::assertSame($exception, $e->getPrevious());
        }
    }

    /**
     * @param array<mixed> $body
     */
    private function exchange(array $body, int $statusCode = 200, ?string $baseCurrency = 'USD'): Exchange
    {
        $response = new Response($statusCode, ['Content-Type' => 'application/json'], (string) json_encode($body));

        return $this->guzzleExchange([$response], $baseCurrency);
    }

    /**
     * @param array<int, mixed> $queue responses or exceptions for Guzzle's MockHandler
     */
    private function guzzleExchange(array $queue, ?string $baseCurrency = 'USD'): Exchange
    {
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $this->requests[] = $request;
        }));

        return (new Exchange('APP_ID', $baseCurrency))->withHttpClient(new Client(['handler' => $stack]));
    }

    /**
     * @return object
     */
    private function clientOf(Exchange $exchange)
    {
        return \Closure::bind(function () {
            return $this->client;
        }, $exchange, Exchange::class)();
    }

    private function lastRequest(): RequestInterface
    {
        self::assertNotEmpty($this->requests, 'No request was sent.');

        return $this->requests[count($this->requests) - 1];
    }

    private function lastUrl(): string
    {
        return (string) $this->lastRequest()->getUri();
    }
}
