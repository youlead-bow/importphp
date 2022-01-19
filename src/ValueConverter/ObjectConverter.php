<?php

namespace Import\ValueConverter;

use Import\Exception\UnexpectedTypeException;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz
 */
class ObjectConverter
{
    protected ?string $propertyPath;

    protected PropertyAccessor $propertyAccessor;

    /**
     * @param string|null $propertyPath
     */
    public function __construct(string $propertyPath = null)
    {
        $this->propertyPath = $propertyPath;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Sets the property
     *
     * @param string $propertyPath
     */
    public function setPropertyPath(string $propertyPath)
    {
        $this->propertyPath = $propertyPath;
    }

    /**
     * Gets the property
     *
     * @return null|string
     */
    public function getPropertyPath(): ?string
    {
        return $this->propertyPath;
    }

    /**
     * {}
     */
    public function __invoke(mixed $input)
    {
        if (!is_object($input)) {
            throw new UnexpectedTypeException($input, 'object');
        }

        if (null === $this->propertyPath && !method_exists($input, '__toString')) {
            throw new RuntimeException;
        }

        if (null === $this->propertyPath) {
            return (string) $input;
        }

        $path = new PropertyPath($this->propertyPath);

        return $this->propertyAccessor->getValue($input, $path);
    }
}
