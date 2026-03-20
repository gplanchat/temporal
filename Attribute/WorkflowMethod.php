<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Attribute;

use Attribute;

/**
 * Marque une méthode comme point d'entrée du workflow.
 *
 * @see https://php.temporal.io/attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class WorkflowMethod
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
