<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * Sends requests from separate PHP processes, each with its own MySQL connection,
 * released together once all have booted. This is a real race: nothing is shared
 * in memory, so only the database can keep the requests apart.
 */
final class RequestRace
{
    private const BOOT_TIMEOUT_SECONDS = 30;

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array{status: int|null, body: array<string, mixed>|null}> status is null when the worker was killed
     */
    public static function run(array $requests): array
    {
        $directory = storage_path('framework/testing/race_'.Str::random(12));
        File::ensureDirectoryExists($directory);

        try {
            $workers = array_map(
                fn (int $id, array $request): Process => self::start([...$request, 'id' => $id, 'dir' => $directory]),
                array_keys($requests),
                $requests,
            );

            self::awaitReady($directory, $workers);
            touch("{$directory}/go");

            return array_map(fn (Process $worker): array => self::result($worker), $workers);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private static function start(array $spec): Process
    {
        $worker = new Process(
            [PHP_BINARY, base_path('tests/Support/request-worker.php'), json_encode($spec, JSON_THROW_ON_ERROR)],
            base_path(),
            timeout: 60,
        );

        $worker->start();

        return $worker;
    }

    /**
     * @param  list<Process>  $workers
     */
    private static function awaitReady(string $directory, array $workers): void
    {
        $deadline = microtime(true) + self::BOOT_TIMEOUT_SECONDS;

        while (count(glob("{$directory}/ready.*")) < count($workers)) {
            if (microtime(true) > $deadline || collect($workers)->contains(fn (Process $worker) => $worker->isTerminated())) {
                $errors = collect($workers)->map(fn (Process $worker) => $worker->getErrorOutput().$worker->getOutput())->filter()->implode("\n");

                throw new RuntimeException("Race workers failed to start.\n{$errors}");
            }

            usleep(5_000);
        }
    }

    /**
     * @return array{status: int|null, body: array<string, mixed>|null}
     */
    private static function result(Process $worker): array
    {
        try {
            $worker->wait();
        } catch (ProcessSignaledException) {
            return ['status' => null, 'body' => null];
        }

        return json_decode($worker->getOutput(), true)
            ?? throw new RuntimeException("Race worker failed.\n{$worker->getErrorOutput()}{$worker->getOutput()}");
    }
}
