<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Zeny\Infrastructure\Config;

const CONFIG_TEST_KEY = 'sk_test_configkey123';
const CONFIG_LIVE_KEY = 'sk_live_configkey123';

const BASE_URL_ERROR = 'baseUrl must be an https URL (http only for localhost, 127.0.0.1 or [::1])';

test('a test key selects the test base URL', function (): void {
    expect(Config::resolve(CONFIG_TEST_KEY)->baseUrl)->toBe('https://snorlax.usezeny.com');
});

test('a live key selects the live base URL', function (): void {
    expect(Config::resolve(CONFIG_LIVE_KEY)->baseUrl)->toBe('https://api.usezeny.com');
});

test('an invalid key is refused', function (): void {
    expect(fn () => Config::resolve('pk_test_x'))->toThrow(InvalidArgumentException::class, 'apiKey must be');
});

test('a custom baseUrl keeps the key mode', function (): void {
    $config = Config::resolve(CONFIG_LIVE_KEY, ['baseUrl' => 'https://pay.example.com']);

    expect([$config->baseUrl, $config->apiKey->mode])->toBe(['https://pay.example.com', 'live']);
});

test('trailing slashes are stripped from baseUrl', function (): void {
    expect(Config::resolve(CONFIG_TEST_KEY, ['baseUrl' => 'https://pay.example.com/v//'])->baseUrl)->toBe('https://pay.example.com/v');
});

foreach (['http://localhost:8080', 'http://127.0.0.1', 'http://[::1]:3000', 'HTTPS://Pay.Example.com'] as $accepted) {
    test("baseUrl {$accepted} is accepted", function () use ($accepted): void {
        expect(Config::resolve(CONFIG_TEST_KEY, ['baseUrl' => $accepted])->baseUrl)->toBe($accepted);
    });
}

$refused = [
    '', '/relative', 'pay.example.com', 'http://pay.example.com', 'ftp://pay.example.com', 'https://',
    'https://user@pay.example.com', 'https://user:pw@pay.example.com', 'https://:pw@pay.example.com',
    'http://localhost.evil.com', 'http://localhost@evil.com', 'http://evil.com/localhost',
    'http://127.0.0.1.evil.com', 'http://[::1].evil.com', 'https://evil.com\\@good.com', "https://pay.example.com\n",
];
foreach ($refused as $index => $baseUrl) {
    test("baseUrl case {$index} is refused", function () use ($baseUrl): void {
        expect(fn () => Config::resolve(CONFIG_TEST_KEY, ['baseUrl' => $baseUrl]))->toThrow(InvalidArgumentException::class, BASE_URL_ERROR);
    });
}

test('a non-string baseUrl is refused', function (): void {
    expect(fn () => Config::resolve(CONFIG_TEST_KEY, ['baseUrl' => 42]))->toThrow(InvalidArgumentException::class, BASE_URL_ERROR);
});

test('maxRetries defaults to 2', function (): void {
    expect(Config::resolve(CONFIG_TEST_KEY)->maxRetries)->toBe(2);
});

test('timeoutMs defaults to 30000', function (): void {
    expect(Config::resolve(CONFIG_TEST_KEY)->timeoutMs)->toBe(30000);
});

test('maxRetries 0 is accepted', function (): void {
    expect(Config::resolve(CONFIG_TEST_KEY, ['maxRetries' => 0])->maxRetries)->toBe(0);
});

foreach ([-1, 1.5, '2'] as $index => $maxRetries) {
    test("maxRetries case {$index} is refused", function () use ($maxRetries): void {
        expect(fn () => Config::resolve(CONFIG_TEST_KEY, ['maxRetries' => $maxRetries]))
            ->toThrow(InvalidArgumentException::class, 'maxRetries must be an integer of 0 or more');
    });
}

foreach ([0, -5, NAN, INF, '100'] as $index => $timeoutMs) {
    test("timeoutMs case {$index} is refused", function () use ($timeoutMs): void {
        expect(fn () => Config::resolve(CONFIG_TEST_KEY, ['timeoutMs' => $timeoutMs]))
            ->toThrow(InvalidArgumentException::class, 'timeoutMs must be a finite number greater than 0');
    });
}

test('a fractional timeoutMs is accepted', function (): void {
    expect(Config::resolve(CONFIG_TEST_KEY, ['timeoutMs' => 2.5])->timeoutMs)->toBe(2.5);
});

test('the default client sets the timeout and refuses redirects', function (): void {
    $client = Config::resolve(CONFIG_TEST_KEY, ['timeoutMs' => 1500])->httpClient;
    $options = (new ReflectionProperty(Client::class, 'config'))->getValue($client);

    expect(is_array($options) ? [$options['timeout'], $options['allow_redirects'], $options['http_errors']] : null)->toBe([1.5, false, false]);
});

test('a given PSR-18 client is used as is', function (): void {
    $client = new Client;

    expect(Config::resolve(CONFIG_TEST_KEY, ['httpClient' => $client])->httpClient)->toBe($client);
});

test('a httpClient that is not PSR-18 is refused', function (): void {
    expect(fn () => Config::resolve(CONFIG_TEST_KEY, ['httpClient' => new stdClass]))->toThrow(InvalidArgumentException::class, 'httpClient must implement');
});

test('dumping a config never prints the key', function (): void {
    expect(print_r(Config::resolve('sk_test_CONFIGDUMPMARK'), true))->not->toContain('CONFIGDUMPMARK');
});
