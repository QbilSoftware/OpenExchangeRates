<?php

namespace Qbil\OpenExchangeRates\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qbil\OpenExchangeRates\Exchange;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ExchangeTest extends TestCase
{
    private const RATES = ['EUR' => 0.92, 'GBP' => 0.79];

    public function testLatestReturnsRates(): void
    {
        $response = $this->jsonResponse(['base' => 'USD', 'rates' => self::RATES]);

        $rates = $this->exchange($response)->latest();

        self::assertSame(self::RATES, $rates);
        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID&base=USD', $response->getRequestUrl());
    }

    public function testRequestArgumentsAreSentAsQueryParameters(): void
    {
        $response = $this->jsonResponse(['rates' => self::RATES]);

        $this->exchange($response)->latest(['symbols' => 'EUR,GBP']);

        self::assertSame(
            'https://openexchangerates.org/api/latest.json?app_id=APP_ID&base=USD&symbols=EUR%2CGBP',
            $response->getRequestUrl()
        );
    }

    public function testRequestBaseOverridesConstructorBase(): void
    {
        $response = $this->jsonResponse(['rates' => self::RATES]);

        $this->exchange($response, 'USD')->latest(['base' => 'GBP']);

        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID&base=GBP', $response->getRequestUrl());
    }

    public function testBaseIsOmittedWhenNotConfigured(): void
    {
        $response = $this->jsonResponse(['rates' => self::RATES]);

        $this->exchange($response, null)->latest();

        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID', $response->getRequestUrl());
    }

    public function testInjectedClientBaseUriIsReplaced(): void
    {
        $response = $this->jsonResponse(['rates' => self::RATES]);
        $client = new MockHttpClient($response, 'https://example.com/other/');

        (new Exchange('APP_ID', null, $client))->latest();

        self::assertSame('https://openexchangerates.org/api/latest.json?app_id=APP_ID', $response->getRequestUrl());
    }

    public function testHistoricalReturnsRatesForDate(): void
    {
        $response = $this->jsonResponse(['rates' => self::RATES]);

        $rates = $this->exchange($response)->historical(['date' => '2017-10-01']);

        self::assertSame(self::RATES, $rates);
        self::assertSame(
            'https://openexchangerates.org/api/historical/2017-10-01.json?app_id=APP_ID&base=USD&date=2017-10-01',
            $response->getRequestUrl()
        );
    }

    public function testBetweenReturnsRatesPerDate(): void
    {
        $series = ['2017-10-01' => self::RATES, '2017-10-02' => self::RATES];
        $response = $this->jsonResponse(['rates' => $series]);

        $rates = $this->exchange($response)->between(['start' => '2017-10-01', 'end' => '2017-10-02']);

        self::assertSame($series, $rates);
        self::assertSame(
            'https://openexchangerates.org/api/time-series.json?app_id=APP_ID&base=USD&start=2017-10-01&end=2017-10-02',
            $response->getRequestUrl()
        );
    }

    public function testConvertReturnsConvertedAmount(): void
    {
        $response = $this->jsonResponse(['response' => 25048.86]);

        $amount = $this->exchange($response)->convert(['amount' => '19999.95', 'from' => 'GBP', 'to' => 'EUR']);

        self::assertSame(25048.86, $amount);
        self::assertStringStartsWith(
            'https://openexchangerates.org/api/convert/19999.95/GBP/EUR?',
            $response->getRequestUrl()
        );
    }

    public function testConvertReturnsFloatForIntegerResponse(): void
    {
        $amount = $this->exchange($this->jsonResponse(['response' => 100]))
            ->convert(['amount' => 100, 'from' => 'USD', 'to' => 'USD']);

        self::assertSame(100.0, $amount);
    }

    public function testCurrenciesReturnsWholeResponse(): void
    {
        $currencies = ['EUR' => 'Euro', 'USD' => 'United States Dollar'];
        $response = $this->jsonResponse($currencies);

        self::assertSame($currencies, $this->exchange($response)->currencies(['show_alternative' => 1]));
        self::assertSame(
            'https://openexchangerates.org/api/currencies.json?app_id=APP_ID&base=USD&show_alternative=1',
            $response->getRequestUrl()
        );
    }

    #[DataProvider('missingParameterProvider')]
    public function testMissingRequiredParameterThrows(string $method, array $args, string $missing): void
    {
        $client = new MockHttpClient(function () {
            self::fail('No request should be sent when a required parameter is missing.');
        });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required parameter `{$missing}` is missing");

        (new Exchange('APP_ID', null, $client))->{$method}($args);
    }

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

    public function testClientErrorIsThrown(): void
    {
        $response = $this->jsonResponse(['error' => true, 'status' => 401, 'message' => 'invalid_app_id'], 401);

        $this->expectException(ClientExceptionInterface::class);

        $this->exchange($response)->latest();
    }

    public function testServerErrorIsThrown(): void
    {
        $this->expectException(ServerExceptionInterface::class);

        $this->exchange($this->jsonResponse([], 503))->currencies();
    }

    public function testCanBeCreatedWithoutClient(): void
    {
        self::assertInstanceOf(Exchange::class, new Exchange('APP_ID'));
    }

    private function exchange(MockResponse $response, ?string $baseCurrency = 'USD'): Exchange
    {
        return new Exchange('APP_ID', $baseCurrency, new MockHttpClient($response));
    }

    private function jsonResponse(array $body, int $statusCode = 200): MockResponse
    {
        return new MockResponse(json_encode($body), [
            'http_code' => $statusCode,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
