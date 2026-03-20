<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Attribute;

use Attribute;

/**
 * Marque une interface ou classe comme activité Temporal.
 *
 * @see https://php.temporal.io/attributes
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_INTERFACE)]
final readonly class ActivityInterface
{
}
