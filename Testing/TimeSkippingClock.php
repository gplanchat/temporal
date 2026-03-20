<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

use Kiboko\Temporal\Workflow\SimulatedClockInterface;

/**
 * Horloge simulée pour les tests de workflows avec timers.
 * Permet d'avancer le temps sans attendre réellement.
 */
final class TimeSkippingClock implements SimulatedClockInterface
{
    private \DateTimeImmutable $currentTime;

    public function __construct(?\DateTimeImmutable $initialTime = null)
    {
        $this->currentTime = $initialTime ?? new \DateTimeImmutable();
    }

    public function now(): \DateTimeImmutable
    {
        return $this->currentTime;
    }

    /**
     * Avance le temps simulé.
     *
     * @param \DateInterval|int|float $duration Intervalle ou durée en secondes (float autorisé, ex. 0.5)
     */
    public function advance(\DateInterval|int|float $duration): void
    {
        if ($duration instanceof \DateInterval) {
            $this->currentTime = $this->currentTime->add($duration);

            return;
        }
        if (\is_int($duration)) {
            $this->currentTime = $this->currentTime->modify("+{$duration} seconds");

            return;
        }
        // PHP n’accepte pas « +1.5 seconds » dans modify() : on décompose secondes entières + microsecondes.
        $whole = (int) floor($duration);
        $frac = $duration - $whole;
        if (0 !== $whole) {
            $this->currentTime = $this->currentTime->modify("+{$whole} seconds");
        }
        if ($frac > 1e-12) {
            $microseconds = (int) round($frac * 1_000_000);
            if (0 !== $microseconds) {
                $this->currentTime = $this->currentTime->modify(sprintf('%+d microseconds', $microseconds));
            }
        }
    }

    /**
     * Définit le temps courant.
     */
    public function set(\DateTimeImmutable $time): void
    {
        $this->currentTime = $time;
    }
}
