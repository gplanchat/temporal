<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Workflow;

/**
 * Détient le contexte workflow courant pour l'exécution.
 * Permet d'injecter le contexte et d'y accéder depuis les workflows.
 */
final class WorkflowContextHolder
{
    private static ?WorkflowContextInterface $current = null;

    public static function set(WorkflowContextInterface $context): void
    {
        self::$current = $context;
    }

    public static function get(): WorkflowContextInterface
    {
        if (self::$current === null) {
            throw new \RuntimeException(
                'Aucun WorkflowContext défini. Utilisez WorkflowRunner::run() ou WorkflowContextHolder::set() avant d\'appeler now(), uuid4(), uuid7() ou ulid().'
            );
        }
        return self::$current;
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    public static function has(): bool
    {
        return self::$current !== null;
    }
}
