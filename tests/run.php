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
    assert_same('2.6.4', $config->get('version'));
    assert_same('verbum/v1', $config->get('api_namespace'));
    assert_true($config->isProduction());
});

test('logger redacts sensitive data recursively', function (): void {
    $redacted = (new Logger())->redact(['api_key' => 'abc', 'nested' => ['token' => 'xyz'], 'safe' => 'ok']);
    assert_same('[redacted]', $redacted['api_key']); assert_same('[redacted]', $redacted['nested']['token']); assert_same('ok', $redacted['safe']);
});

test('capabilities include core permissions and writer role', function (): void { assert_same([Capabilities::ACCESS, Capabilities::MANAGE, Capabilities::MANAGE_SETTINGS], (new Capabilities())->all()); assert_same('verbum_writer', Capabilities::WRITER_ROLE); });
test('authentication error maps to unauthorized', function (): void { $error = new AuthenticationError('Acesso não autorizado.'); assert_same(401, $error->status()); assert_same('unauthorized', $error->errorCode()); });

test('health endpoint returns ok and version', function (): void {
    $controller = new RestController(new Config(), new ResponseFactory(new Config()), new Capabilities()); $response = $controller->health(); $data = $response->get_data();
    assert_same(200, $response->get_status()); assert_same(true, $data['success']); assert_same('ok', $data['data']['status']); assert_same('2.6.4', $data['data']['version']);
});

test('me endpoint rejects visitors', function (): void {
    global $verbum_test_logged_in; $verbum_test_logged_in = false; $controller = new RestController(new Config(), new ResponseFactory(new Config()), new Capabilities()); $response = $controller->me(); $data = $response->get_data();
    assert_same(401, $response->get_status()); assert_same(false, $data['success']); assert_same('unauthorized', $data['error']['code']);
});

test('shortcode avoids assets during JSON editor requests', function (): void {
    global $verbum_test_enqueued, $verbum_test_json_request; $verbum_test_enqueued = []; $verbum_test_json_request = true;
    assert_same('<div class="verbum-app" data-verbum-app></div>', (new FrontendAssets())->shortcode()); assert_same([], $verbum_test_enqueued); $verbum_test_json_request = false;
});

test('shortcode enqueues only base styles before lazy stage assets', function (): void {
    global $verbum_test_enqueued, $verbum_test_json_request; $verbum_test_enqueued = []; $verbum_test_json_request = false;
    assert_same('<div class="verbum-app" data-verbum-app></div>', (new FrontendAssets())->shortcode()); $serialized = json_encode($verbum_test_enqueued);
    foreach (['verbum.css','library.css','technical.css','dashboard-official.css','dashboard-polish.css','sidebar-profile.css','minhas-obras.css','auth-profile.css','profile-polish.css','verbum-app.js'] as $asset) assert_true(strpos((string) $serialized, $asset) !== false, 'Missing base asset: ' . $asset);
    foreach (['identification.css','planning-stage.css','development-stage.css','general-review.css','publication-stage.css'] as $asset) assert_true(strpos((string) $serialized, $asset) === false, 'Stage stylesheet must be lazy: ' . $asset);
});

test('plugin registers shortcode and rest hooks', function (): void {
    global $verbum_test_shortcodes, $verbum_test_actions; $plugin = new Plugin(new Container(), new Config()); $plugin->register();
    assert_true(isset($verbum_test_shortcodes['verbum_app'])); assert_true(isset($verbum_test_actions['rest_api_init']));
});

test('private storage types exist for projects books chapters and research', function (): void {
    global $verbum_test_post_types; $verbum_test_post_types = []; (new LibraryPostTypes())->register();
    foreach ([LibraryPostTypes::PROJECT, LibraryPostTypes::BOOK, LibraryPostTypes::CHAPTER, LibraryPostTypes::RESEARCH] as $type) { assert_true(isset($verbum_test_post_types[$type]), 'Missing post type: ' . $type); assert_same(false, $verbum_test_post_types[$type]['public']); }
});

test('Sprint 19 REST routes are registered', function (): void {
    global $verbum_test_actions, $verbum_test_routes; $verbum_test_actions = []; $verbum_test_routes = []; $plugin = new Plugin(new Container(), new Config()); $plugin->register(); foreach ($verbum_test_actions['rest_api_init'] ?? [] as $callback) $callback();
    foreach([
        'verbum/v1/auth/login','verbum/v1/profile','verbum/v1/library','verbum/v1/projects','verbum/v1/books',
        'verbum/v1/books/(?P<id>\\d+)/workspace','verbum/v1/books/(?P<id>\\d+)/identification','verbum/v1/books/(?P<id>\\d+)/identification/complete',
        'verbum/v1/books/(?P<id>\\d+)/project-stage','verbum/v1/books/(?P<id>\\d+)/project-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/planning-stage','verbum/v1/books/(?P<id>\\d+)/planning-stage/generate-chapters','verbum/v1/books/(?P<id>\\d+)/planning-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/development-stage','verbum/v1/books/(?P<id>\\d+)/development-stage/complete','verbum/v1/books/(?P<id>\\d+)/development-stage/structure-preview','verbum/v1/books/(?P<id>\\d+)/development-stage/structure-sync','verbum/v1/books/(?P<id>\\d+)/development-stage/order','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)',
        'verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/preparation','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/preparation/complete',
        'verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/research','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/research/sources','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/research/sources/(?P<source_id>\\d+)','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/research/complete',
        'verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/writing','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/writing/complete','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/writing/assist',
        'verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/revision','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/revision/issues','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/revision/issues/(?P<issue_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/revision/complete','verbum/v1/books/(?P<id>\\d+)/chapters/(?P<chapter_id>\\d+)/revision/assist',
        'verbum/v1/books/(?P<id>\\d+)/general-review','verbum/v1/books/(?P<id>\\d+)/general-review/reading','verbum/v1/books/(?P<id>\\d+)/general-review/issues','verbum/v1/books/(?P<id>\\d+)/general-review/issues/(?P<issue_id>[a-zA-Z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/general-review/complete','verbum/v1/books/(?P<id>\\d+)/general-review/assist','verbum/v1/books/(?P<id>\\d+)/general-review/substeps/(?P<substep>[a-z-]+)/complete',
        'verbum/v1/books/(?P<id>\\d+)/versions-stage','verbum/v1/books/(?P<id>\\d+)/versions-stage/versions','verbum/v1/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)/duplicate','verbum/v1/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)/restore','verbum/v1/books/(?P<id>\\d+)/versions-stage/versions/(?P<version_id>[A-Za-z0-9_-]+)/audit-baseline','verbum/v1/books/(?P<id>\\d+)/versions-stage/compare','verbum/v1/books/(?P<id>\\d+)/versions-stage/complete','verbum/v1/books/(?P<id>\\d+)/versions-stage/validation',
        'verbum/v1/books/(?P<id>\\d+)/audit-stage','verbum/v1/books/(?P<id>\\d+)/audit-stage/findings','verbum/v1/books/(?P<id>\\d+)/audit-stage/findings/(?P<finding_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/audit-stage/report','verbum/v1/books/(?P<id>\\d+)/audit-stage/assist','verbum/v1/books/(?P<id>\\d+)/audit-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/editorial-desk','verbum/v1/books/(?P<id>\\d+)/editorial-desk/adjustments','verbum/v1/books/(?P<id>\\d+)/editorial-desk/adjustments/(?P<adjustment_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/editorial-desk/assist','verbum/v1/books/(?P<id>\\d+)/editorial-desk/complete','verbum/v1/books/(?P<id>\\d+)/editorial-desk/preparation',
        'verbum/v1/books/(?P<id>\\d+)/layout-stage','verbum/v1/books/(?P<id>\\d+)/layout-stage/preview','verbum/v1/books/(?P<id>\\d+)/layout-stage/issues','verbum/v1/books/(?P<id>\\d+)/layout-stage/issues/(?P<issue_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/layout-stage/proofs','verbum/v1/books/(?P<id>\\d+)/layout-stage/assist','verbum/v1/books/(?P<id>\\d+)/layout-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/legal-stage','verbum/v1/books/(?P<id>\\d+)/legal-stage/documents','verbum/v1/books/(?P<id>\\d+)/legal-stage/documents/(?P<document_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/legal-stage/third-party','verbum/v1/books/(?P<id>\\d+)/legal-stage/third-party/(?P<item_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/legal-stage/issues','verbum/v1/books/(?P<id>\\d+)/legal-stage/issues/(?P<issue_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/legal-stage/proofs','verbum/v1/books/(?P<id>\\d+)/legal-stage/assist','verbum/v1/books/(?P<id>\\d+)/legal-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/publication-stage','verbum/v1/books/(?P<id>\\d+)/publication-stage/journey','verbum/v1/books/(?P<id>\\d+)/publication-stage/channels','verbum/v1/books/(?P<id>\\d+)/publication-stage/channels/(?P<channel_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/publication-stage/tasks','verbum/v1/books/(?P<id>\\d+)/publication-stage/tasks/(?P<task_id>[A-Za-z0-9_-]+)','verbum/v1/books/(?P<id>\\d+)/publication-stage/updates','verbum/v1/books/(?P<id>\\d+)/publication-stage/assist','verbum/v1/books/(?P<id>\\d+)/publication-stage/complete',
        'verbum/v1/books/(?P<id>\\d+)/cover','verbum/v1/books/(?P<id>\\d+)/archive',
    ] as $route) assert_true(isset($verbum_test_routes[$route]), 'Missing REST route: ' . $route);
});

$failures = 0;
foreach ($tests as $name => $callback) { try { $callback(); echo "PASS {$name}\n"; } catch (Throwable $throwable) { $failures++; echo "FAIL {$name}: {$throwable->getMessage()}\n"; } }
if ($failures > 0) exit(1);
