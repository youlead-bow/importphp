<?php

namespace Import\Step;

use ArrayAccess;
use Import\Exception\UnexpectedTypeException;
use Import\Step;
use Traversable;

/**
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class ArrayCheckStep implements Step
{
    /**
     * {@inheritdoc}
     */
    public function process(mixed $item, callable $next): ?bool
    {
        if (!is_array($item) && !($item instanceof ArrayAccess && $item instanceof Traversable)) {
            throw new UnexpectedTypeException($item, 'array');
        }

        return $next($item);
    }
}
