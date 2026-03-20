<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Trait pour les workflows utilisant le Saga pattern.
 *
 * Fournit une méthode createSaga() pour instancier une Saga configurée.
 */
trait SagaAwareTrait
{
    /**
     * Crée une nouvelle instance Saga pour le workflow.
     */
    protected function createSaga(): Saga
    {
        return new Saga();
    }
}
