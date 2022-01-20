<?php

namespace Import\Step;

use Import\Exception\MappingException;
use Import\Step;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class MappingStep implements CountableStep
{
    private array $mappings;

    private PropertyAccessor|PropertyAccessorInterface $accessor;

    /**
     * @param array $mappings
     * @param PropertyAccessorInterface|null $accessor
     */
    public function __construct(array $mappings = [], PropertyAccessorInterface $accessor = null)
    {
        $this->mappings = $mappings;
        $this->accessor = $accessor ?: new PropertyAccessor();
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @return $this
     */
    public function map(string $from, string $to): static
    {
        $this->mappings[$from] = $to;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws MappingException
     */
    public function process(mixed $item, callable $next): ?bool
    {
        try {
            foreach ($this->mappings as $from => $to) {
                $value = $this->accessor->getValue($item, $from);
                $this->accessor->setValue($item, $to, $value);

                $from = str_replace(['[',']'], '', $from);

                // Check if $item is an array, because properties can't be unset.
                // So we don't call unset for objects to prevent side effects.
                // Also, we don't have to unset the property if the key is the same
                if (is_array($item) && array_key_exists($from, $item) && $from !== str_replace(['[',']'], '', $to)) {
                    unset($item[$from]);
                }
            }
        } catch (NoSuchPropertyException|UnexpectedTypeException $exception) {
            throw new MappingException('Unable to map item', null, $exception);
        }

        return $next($item);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->mappings);
    }
}
