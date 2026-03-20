<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

/**
 * Boucle **test / démo** : {@see MessengerWorkerTicker::tick()} + sonde de résultat (ex. workflow gRPC)
 * jusqu’à valeur non nulle ou timeout.
 *
 * Factorise le motif utilisé par les E2E « mono-processus ». Non API Symfony stable — même avertissement
 * que {@see MessengerWorkerTicker}.
 *
 * @experimental
 */
final class MessengerTemporalTickerLoop
{
    /**
     * @template T
     *
     * @param callable(): T|null $tryGetResult Retourne la valeur finale ou `null` tant qu’il faut continuer à pumper.
     * @param int $sleepMicroseconds Pause entre itérations en µs (`0` accepté pour les tests ; défaut 50_000).
     *
     * @return T|null `null` si timeout sans résultat
     */
    public static function pumpUntil(
        MessengerWorkerTicker $ticker,
        callable $tryGetResult,
        float $timeoutSeconds,
        int $sleepMicroseconds = 50_000,
    ): mixed {
        if ($timeoutSeconds <= 0) {
            return null;
        }

        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            $ticker->tick();
            $step = $tryGetResult();
            if (null !== $step) {
                return $step;
            }
            usleep($sleepMicroseconds);
        }

        return null;
    }
}
