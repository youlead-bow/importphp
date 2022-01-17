<?php

namespace Import\Reader;

use Import\Exception\ReaderException;
use Import\Reader;

/**
 * Takes multiple readers for processing in the same workflow
 *
 * @author Adam Paterson <hello@adampaterson.co.uk>
 * @author Aydin Hassan <aydin@hotmail.co.uk>
 */
class OneToManyReader implements CountableReader
{
    protected Reader $leftReader;
    protected Reader $rightReader;
    protected string $leftJoinField;
    protected ?string $rightJoinField;

    /**
     * Key to nest the rightRows under
     */
    protected string $nestKey;

    /**
     * @param Reader $leftReader
     * @param Reader $rightReader
     * @param string $nestKey
     * @param string $leftJoinField
     * @param string|null $rightJoinField
     */
    public function __construct(
        Reader $leftReader,
        Reader $rightReader,
        string $nestKey,
        string $leftJoinField,
        string $rightJoinField = null
    ) {
        if (is_null($rightJoinField)) {
            $rightJoinField = $leftJoinField;
        }

        $this->leftJoinField  = $leftJoinField;
        $this->rightJoinField = $rightJoinField;
        $this->leftReader     = $leftReader;
        $this->rightReader    = $rightReader;
        $this->nestKey        = $nestKey;
    }

    /**
     * Create an array of children in the leftRow,
     * with the data returned from the right reader
     * Where the ID fields Match
     *
     * @throws ReaderException
     */
    public function current(): array
    {
        $leftRow = $this->leftReader->current();

        if (array_key_exists($this->nestKey, $leftRow)) {
            throw new ReaderException(
                sprintf(
                    'Left Row: "%s" Reader already contains a field named "%s". Please choose a different nest key field',
                    $this->key(),
                    $this->nestKey
                )
            );
        }
        $leftRow[$this->nestKey] = [];

        $leftId     = $this->getRowId($leftRow, $this->leftJoinField);
        $rightRow   = $this->rightReader->current();
        $rightId    = $this->getRowId($rightRow, $this->rightJoinField);

        while ($leftId == $rightId && $this->rightReader->valid()) {

            $leftRow[$this->nestKey][] = $rightRow;
            $this->rightReader->next();

            $rightRow = $this->rightReader->current();

            if($this->rightReader->valid()) {
                $rightId = $this->getRowId($rightRow, $this->rightJoinField);
            }
        }

        return $leftRow;
    }

    /**
     * @throws ReaderException
     */
    protected function getRowId(array $row, string $idField): mixed
    {
        if (!array_key_exists($idField, $row)) {
            throw new ReaderException(
                sprintf(
                    'Row: "%s" has no field named "%s"',
                    $this->key(),
                    $idField
                )
            );
        }

        return $row[$idField];
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->leftReader->next();
        //right reader is iterated in current() method.
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->leftReader->key();
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return $this->leftReader->valid() && $this->rightReader->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->leftReader->rewind();
        $this->rightReader->rewind();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->leftReader->count();
    }
}
