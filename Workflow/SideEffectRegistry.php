<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Registre d’effets de bord **ordonnés** pour workflows : première exécution exécute le callable et mémorise
 * le résultat ; en mode « replay » simulé, les valeurs enregistrées sont rejouées dans le même ordre.
 *
 * **PoC / tests** : ne remplace pas l’enregistrement d’événements côté serveur Temporal. À brancher sur le
 * futur runtime d’historique pour une parité SDK.
 *
 * @see docs/ROADMAP_TRANSPORT_SDK_WORKFLOWS.md
 */
final class SideEffectRegistry
{
    /** @var list<mixed> */
    private array $recorded = [];

    private int $readIndex = 0;

    private bool $replaying = false;

    /**
     * Active le mode replay : les prochains {@see self::run()} consomment $sequence dans l’ordre.
     *
     * @param list<mixed> $sequence
     */
    public function enterReplayMode(array $sequence): void
    {
        $this->replaying = true;
        $this->recorded = array_values($sequence);
        $this->readIndex = 0;
    }

    public function reset(): void
    {
        $this->recorded = [];
        $this->readIndex = 0;
        $this->replaying = false;
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function run(callable $fn): mixed
    {
        if ($this->replaying) {
            if ($this->readIndex >= \count($this->recorded)) {
                throw new \LogicException(
                    'SideEffectRegistry : plus de valeur enregistrée pour l’index '.$this->readIndex.' (replay incomplet).',
                );
            }

            return $this->recorded[$this->readIndex++];
        }

        $value = $fn();
        $this->recorded[] = $value;

        return $value;
    }

    /**
     * @return list<mixed>
     */
    public function exportRecorded(): array
    {
        return $this->recorded;
    }

    public function isReplaying(): bool
    {
        return $this->replaying;
    }
}
