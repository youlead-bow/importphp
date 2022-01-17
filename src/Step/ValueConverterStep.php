<?php

namespace Import\Step;

use Import\Step;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ValueConverterStep implements Step
{
    private array $converters = [];

    /**
     * @param string $property
     * @param callable $converter
     *
     * @return $this
     */
    public function add(string $property, callable $converter): static
    {
        $this->converters[$property][] = $converter;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function process(mixed $item, callable $next): bool
    {
        $accessor = new PropertyAccessor();

        foreach ($this->converters as $property => $converters) {
            foreach ($converters as $converter) {
                $orgValue = $accessor->getValue($item, $property);
                $value = call_user_func($converter, $orgValue);
                $accessor->setValue($item, $property, $value);
            }
        }

        return $next($item);
    }
}
