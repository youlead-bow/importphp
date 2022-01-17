<?php

namespace Import\Exception;

use Import\Exception;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ValidationException extends \Exception implements Exception
{
    private ConstraintViolationListInterface $violations;

    private int $lineNumber;

    /**
     * @param ConstraintViolationListInterface $list
     * @param integer                          $line
     */
    public function __construct(ConstraintViolationListInterface $list, $line)
    {
        $this->violations = $list;
        $this->lineNumber = $line;

        $this->message = $this->createMessage($list, $line);
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function getLineNumber(): integer
    {
        return $this->lineNumber;
    }

    /**
     * @param ConstraintViolationListInterface|ConstraintViolationInterface[] $list
     * @param integer $line
     */
    private function createMessage(ConstraintViolationListInterface $list, $line): string
    {
        $messages = [];
        foreach ($list as $violation) {
            $messages[] = $violation->getMessage();
        }

        return sprintf('Line %d: %s', $line, implode(', ', $messages));
    }
}
