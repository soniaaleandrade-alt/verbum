<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use VerbumStudio\Api\ResponseFactory;
use VerbumStudio\Api\RestController;
use VerbumStudio\Auth\Capabilities;
use VerbumStudio\Core\Config;
use VerbumStudio\Core\Container;
use VerbumStudio\Core\Plugin;
use VerbumStudio\Exceptions\AuthenticationError;
use VerbumStudio\Library\LibraryPostTypes;
use VerbumStudio\Services\FrontendAssets;
use VerbumStudio\Support\Logger;

$tests = [];
function test(string $name, callable $callback): void { global $tests; $tests[$name] = $callback; }
function assert_true(bool $condition, string $message = 'Expected condition to be true'): void { if (! $condition) throw new RuntimeException($message); }
function assert_same($expected, $actual, string $message = ''): void { if ($expected !== $actual) throw new RuntimeException($message ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }

test('config exposes core defaults and production mode', function (): void {
    $config = new Config(['environment' => 'production']);
    assert_same('1.0.7', $config->get('version'));
    assert_same('verbum/v1', $config->get('api_namespace'));
    assert_true($config->isProduction());
});

test('logger redacts sensitive data recursively', function (): void {
    $redacted = (new Logger())->redact(['api_key' => 'abc', 'nested' => ['token' => 'xyz'], 'safe' => 'ok']);
    assert_same('[redacted]', $redacted['api_key']);
    assert_same('[redacted]', $redacted['nested']['token']);
    assert_same('ok', $redacted['safe']);
});

test('capabilities include core permissions', function (): void {
    assert_same([Capabilities::ACCESS, Capabilities::MANAGE, Capabilities::MANAGE_SETTINGS], (new Capabilities())->all());
});

test('authentication error maps to unauthorized', function (): void {
    $error = new AuthenticationError('Acesso não autorizado.');
    assert_same(401, $error->status());
    assert_same('unauthorized', $error->errorCode());
});

test('health endpoint returns ok and version', function (): void {
    $controller = new RestController(new Config(), new ResponseFactory(new Config()), new Capabilities());
    $response = $controller->health();
    $data = $response->get_data();
    assert_same(200, $response->get_status());
    assert_same(true, $data['success']);
    assert_same('ok', $data['data']['status']);
    assert_same('1.0.7', $data['data']['version']);
});

test('me endpoint rejects visitors', function (): void {
    global $verbum_test_logged_in;
    $verbum_test_logged_in = false;
    $controller = new RestController(new Config(), new ResponseFactory(new Config()), new Capabilities());
    $response = $controller->me();
    $data = $response->get_data();
    assert_same(401, $response->get_status());
    assert_same(false, $data['success']);
    assert_same('unauthorized', $data['error']['code']);
});

test('me endpoint returns the account full name for authorized users', function (): void {
    global $verbum_test_logged_in, $verbum_test_caps;
    $verbum_test_logged_in = true;
    $verbum_test_caps = [Capabilities::ACCESS];
    $controller = new RestController(new Config(), new ResponseFactory(new Config()), new Capabilities());
    $data = $controller->me()->get_data();
    assert_same(true, $data['success']);
    assert_same('7', $data['data']['id']);
    assert_same('Sonia Andrade', $data['data']['name']);
});

test('shortcode avoids assets during JSON editor requests', function (): void {
    global $verbum_test_enqueued, $verbum_test_json_request;
    $verbum_test_enqueued = [];
    $verbum_test_json_request = true;
    assert_same('<div class="verbum-app" data-verbum-app></div>', (new FrontendAssets())->shortcode());
    assert_same([], $verbum_test_enqueued);
    $verbum_test_json_request = false;
});

test('shortcode enqueues assets on normal frontend rendering', function (): void {
    global $verbum_test_enqueued, $verbum_test_json_request;
    $verbum_test_enqueued = [];
    $verbum_test_json_request = false;
    assert_same('<div class="verbum-app" data-verbum-app></div>', (new FrontendAssets())->shortcode());
    assert_true(count($verbum_test_enqueued) >= 3);
});

test('plugin registers shortcode and rest hooks', function (): void {
    global $verbum_test_shortcodes, $verbum_test_actions;
    $plugin = new Plugin(new Container(), new Config());
    $plugin->register();
    assert_true(isset($verbum_test_shortcodes['verbum_app']));
    assert_true(isset($verbum_test_actions['rest_api_init']));
});

test('private storage types exist for projects and books', function (): void {
    global $verbum_test_post_types;
    $verbum_test_post_types = [];
    (new LibraryPostTypes())->register();
    assert_true(isset($verbum_test_post_types[LibraryPostTypes::PROJECT]));
    assert_true(isset($verbum_test_post_types[LibraryPostTypes::BOOK]));
    assert_same(false, $verbum_test_post_types[LibraryPostTypes::PROJECT]['public']);
    assert_same(false, $verbum_test_post_types[LibraryPostTypes::BOOK]['public']);
});

test('Banco de Obras, workspace, Identification and Projeto da Obra REST routes are registered', function (): void {
    global $verbum_test_actions, $verbum_test_routes;
    $verbum_test_actions = [];
    $verbum_test_routes = [];
    $plugin = new Plugin(new Container(), new Config());
    $plugin->register();
    foreach ($verbum_test_actions['rest_api_init'] ?? [] as $callback) $callback();
    foreach ([
        'verbum/v1/library',
        'verbum/v1/projects',
        'verbum/v1/projects/(?P<id>\\d+)',
        'verbum/v1/projects/(?P<id>\\d+)/archive',
        'verbum/v1/books',
        'verbum/v1/books/(?P<id>\\d+)',
        'verbum/v1/books/(?P<id>\\d+)/workspace',
        'verbum/v1/books/(?P<id>\\d+)/identification',
        'verbum/v1/books/(?P<id>\\d+)/identification/complete',
        'verbum/v1/books/(?P<id>\\d+)/project-stage',
        'verbum/v1/books/(?P<id>\\d+)/project-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/cover',
        'verbum/v1/books/(?P<id>\\d+)/archive',
    ] as $route) assert_true(isset($verbum_test_routes[$route]), 'Missing REST route: ' . $route);
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try { $callback(); echo "PASS {$name}\n"; }
    catch (Throwable $throwable) { $failures++; echo "FAIL {$name}: {$throwable->getMessage()}\n"; }
}
if ($failures > 0) exit(1);
