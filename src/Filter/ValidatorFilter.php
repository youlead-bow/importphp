<?php

namespace Import\Filter;

use Import\Exception\ValidationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ValidatorFilter
{
    private ValidatorInterface $validator;

    private bool $throwExceptions = false;

    private int $line = 1;

    private bool $strict = true;

    private array $constraints = [];

    private array $violations = [];

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
     */
    public function add(string $field, Constraint $constraint)
    {
        if (!isset($this->constraints[$field])) {
            $this->constraints[$field] = [];
        }

        $this->constraints[$field][] = $constraint;
    }

    /**
     * @param boolean $flag
     */
    public function throwExceptions(bool $flag = true)
    {
        $this->throwExceptions = $flag;
    }

    /**
     * @param boolean $strict
     */
    public function setStrict(bool $strict)
    {
        $this->strict = $strict;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * @param array $item
     * @return bool
     * @throws ValidationException
     */
    public function __invoke(array $item): bool
    {
        if (!$this->strict) {
            // Only validate properties which have a constraint.
            $temp = array_intersect(array_keys($item), array_keys($this->constraints));
            $item = array_intersect_key($item, array_flip($temp));
        }

        $constraints = new Constraints\Collection($this->constraints);
        $list = $this->validator->validate($item, $constraints);
        $currentLine = $this->line++;

        if (count($list) > 0) {
            $this->violations[$currentLine] = $list;

            if ($this->throwExceptions) {
                throw new ValidationException($list, $currentLine);
            }
        }

        return 0 === count($list);
    }
}
