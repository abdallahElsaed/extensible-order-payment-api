<?php

declare(strict_types=1);

/*
 * One racing client: boots the real application, waits at the starting line, then
 * sends a single request through the full HTTP kernel. Driven by RequestRace.
 */

use App\Enums\Payment\PaymentMethod;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$spec = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->instance('request', Request::create('/'));
$kernel->bootstrap();

$database = DB::connection()->getDatabaseName();

if (! str_ends_with($database, '_test')) {
    fwrite(STDERR, "Refusing to run against [{$database}].");
    exit(1);
}

if (isset($spec['gateway'])) {
    $app->singleton(PaymentGatewayRegistry::class, fn ($app) => new PaymentGatewayRegistry($app, [
        PaymentMethod::CreditCard->value => $spec['gateway'],
    ]));
}

if (isset($spec['pause_after_query'])) {
    $paused = false;

    DB::listen(function (QueryExecuted $query) use ($spec, &$paused): void {
        if (! $paused && str_contains($query->sql, $spec['pause_after_query'])) {
            $paused = true;
            touch("{$spec['dir']}/paused");
            usleep(800_000);
        }
    });
}

touch("{$spec['dir']}/ready.{$spec['id']}");

while (! file_exists("{$spec['dir']}/go")) {
    usleep(500);
}

if ($spec['wait_for_pause'] ?? false) {
    $deadline = microtime(true) + 5;

    while (! file_exists("{$spec['dir']}/paused") && microtime(true) < $deadline) {
        usleep(500);
    }
}

$server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

foreach ($spec['headers'] ?? [] as $name => $value) {
    $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
}

$response = $kernel->handle(Request::create(
    uri: $spec['uri'],
    method: $spec['method'],
    server: $server,
    content: json_encode($spec['body'] ?? [], JSON_THROW_ON_ERROR),
));

echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]);
