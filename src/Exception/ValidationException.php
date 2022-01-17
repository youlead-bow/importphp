<?php

namespace Import\Exception;

use Import\Exception;
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
     * @param integer $line
     */
    public function __construct(ConstraintViolationListInterface $list, int $line)
    {
        parent::__construct();
        $this->violations = $list;
        $this->lineNumber = $line;

        $this->message = $this->createMessage($list, $line);
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @param ConstraintViolationListInterface $list
     * @param integer $line
     * @return string
     */
    private function createMessage(ConstraintViolationListInterface $list, int $line): string
    {
        $messages = [];
        foreach ($list as $violation) {
            $messages[] = $violation->getMessage();
        }

        return sprintf('Line %d: %s', $line, implode(', ', $messages));
    }
}
