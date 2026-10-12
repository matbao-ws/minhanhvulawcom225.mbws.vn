<?php

/*
 * One racer for CheckoutConcurrencyTest, run in its own PHP process with its own
 * database connection: `php tests/Support/Concurrency/checkout-race.php <payload.json>`.
 *
 * It waits at a MySQL named lock that the test holds, then sends the checkout through
 * the real HTTP kernel the moment the test lets go, and prints the result as JSON.
 * Test-only: it is not part of a core release (tests/ is not in CoreManifest::CORE).
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$payload = json_decode((string) file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);

// The barrier: blocks until the test releases the lock, then hands it straight on.
DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$payload['barrier']]);
DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$payload['barrier']]);

$request = Request::create('/api/public/orders/checkout', 'POST', server: [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_IDEMPOTENCY_KEY' => $payload['key'],
    'REMOTE_ADDR' => '127.0.0.'.random_int(2, 250),
], content: json_encode($payload['body']));

$response = $kernel->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'replayed' => $response->headers->get('Idempotent-Replayed'),
    'body' => json_decode((string) $response->getContent(), true),
]);

$kernel->terminate($request, $response);
