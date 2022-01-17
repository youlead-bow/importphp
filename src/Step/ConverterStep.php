<?php

namespace Import\Step;

use Import\Step;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ConverterStep implements Step
{
    private array $converters;

    /**
     * @param array $converters
     */
    public function __construct(array $converters = [])
    {
        foreach ($converters as $converter) {
            $this->add($converter);
        }
    }

    /**
     * @param callable $converter
     * @return ConverterStep
     */
    public function add(callable $converter): static
    {
        $this->converters[] = $converter;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function process(mixed $item, callable $next): bool
    {
        foreach ($this->converters as $converter) {
            $item = call_user_func($converter, $item);
        }

        return $next($item);
    }
}
