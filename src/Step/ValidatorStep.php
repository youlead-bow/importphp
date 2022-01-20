<?php

namespace Import\Step;

use Import\Exception\ValidationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ValidatorStep implements PriorityStep, CountableStep
{
    private array $constraints = [];

    private array $violations = [];

    private bool $throwExceptions = false;

    private int $line = 0;

    private int $counter = 0;

    private ValidatorInterface $validator;

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param string $field
     * @param Constraint $constraint
     *
     * @return $this
     */
    public function add(string $field, Constraint $constraint): static
    {
        if (!isset($this->constraints['fields'][$field])) {
            $this->constraints['fields'][$field] = [];
        }

        $this->constraints['fields'][$field][] = $constraint;
        $this->counter++;

        return $this;
    }

    /**
     * @param boolean $flag
     */
    public function throwExceptions(bool $flag = true)
    {
        $this->throwExceptions = $flag;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * Add additional options to the Collection constraint.
     *
     * @param string $option
     * @param mixed  $optionValue
     *
     * @return $this
     */
    public function addOption(string $option, mixed $optionValue): static
    {
        $this->constraints[$option] = $optionValue;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws ValidationException
     */
    public function process(mixed $item, callable $next): ?bool
    {
        $this->line++;

        if (count($this->constraints) > 0) {
            $constraints = new Constraints\Collection($this->constraints);
            $list = $this->validator->validate($item, $constraints);
        } else {
            $list = $this->validator->validate($item);
        }

        if (count($list) > 0) {
            $this->violations[$this->line] = $list;

            if ($this->throwExceptions) {
                throw new ValidationException($list, $this->line);
            }
        }

        if (0 === count($list)) {
            return $next($item);
        }

        return false;
    }

    /**
     * {}
     */
    public function getPriority(): int
    {
        return 128;
    }

    public function count(): int
    {
        return $this->counter;
    }
}
