<?php

declare(strict_types=1);


namespace Import\Reader;


use Import\Exception\DuplicateHeadersException;
use SeekableIterator;
use SplFileObject;

class CsvReader implements CountableReader, SeekableIterator
{
    const DUPLICATE_HEADERS_INCREMENT = 1;
    const DUPLICATE_HEADERS_MERGE = 2;

    /**
     * Number of the row that contains the column names
     */
    protected ?int $headerRowNumber;

    /**
     * CSV file
     */
    protected SplFileObject $file;

    /**
     * Column headers as read from the CSV file
     */
    protected array $columnHeaders = [];

    /**
     * Number of column headers, stored and re-used for performance
     *
     * In case of duplicate headers, this is always the number of unmerged headers.
     */
    protected int $headersCount;

    /**
     * Total number of rows in the CSV file
     */
    protected ?int $count;

    /**
     * Faulty CSV rows
     */
    protected array $errors = [];

    /**
     * Strict parsing - skip any lines mismatching header length
     */
    protected bool $strict = true;

    /**
     * How to handle duplicate headers
     */
    protected int $duplicateHeadersFlag;

    /**
     * @param SplFileObject $file
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function __construct(SplFileObject $file, string $delimiter = ',', string $enclosure = '"', string $escape = '\\')
    {
        ini_set('auto_detect_line_endings', '1');

        $this->file = $file;
        $this->file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::READ_AHEAD |
            SplFileObject::DROP_NEW_LINE
        );
        $this->file->setCsvControl(
            $delimiter,
            $enclosure,
            $escape
        );
    }

    /**
     * Return the current row as an array
     *
     * If a header row has been set, an associative array will be returned
     */
    public function current(): ?array
    {
        // If the CSV has no column headers just return the line
        if (empty($this->columnHeaders)) {
            return $this->file->current();
        }

        // Since the CSV has column headers use them to construct an associative array for the columns in this line
        do {
            $line = $this->file->current();

            // In non-strict mode pad/slice the line to match the column headers
            if (!$this->isStrict()) {
                if ($this->headersCount > count($line)) {
                    $line = array_pad($line, $this->headersCount, null); // Line too short
                } else {
                    $line = array_slice($line, 0, $this->headersCount); // Line too long
                }
            }

            // See if values for duplicate headers should be merged
            if (self::DUPLICATE_HEADERS_MERGE === $this->duplicateHeadersFlag) {
                $line = $this->mergeDuplicates($line);
            }

            // Count the number of elements in both: they must be equal.
            if (count($this->columnHeaders) === count($line)) {
                return array_combine(array_keys($this->columnHeaders), $line);
            }

            // They are not equal, so log the row as error and skip it.
            if ($this->valid()) {
                $this->errors[$this->key()] = $line;
                $this->next();
            }
        } while ($this->valid());

        return null;
    }

    /**
     * Get column headers
     *
     * @return array
     */
    public function getColumnHeaders(): array
    {
        return array_keys($this->columnHeaders);
    }

    /**
     * Set column headers
     *
     * @param array $columnHeaders
     */
    public function setColumnHeaders(array $columnHeaders)
    {
        $this->columnHeaders = array_count_values($columnHeaders);
        $this->headersCount = count($columnHeaders);
    }

    /**
     * Set header row number
     *
     * @param integer $rowNumber Number of the row that contains column header names
     * @param integer|null $duplicates How to handle duplicates (optional). One of:
     *                        - CsvReader::DUPLICATE_HEADERS_INCREMENT;
     *                        increments duplicates (dup, dup1, dup2 etc.)
     *                        - CsvReader::DUPLICATE_HEADERS_MERGE; merges
     *                        values for duplicate headers into an array
     *                        (dup => [value1, value2, value3])
     *
     * @throws DuplicateHeadersException If duplicate headers are encountered
     *                                   and no duplicate handling has been
     *                                   specified
     */
    public function setHeaderRowNumber(int $rowNumber, ?int $duplicates = null)
    {
        $this->duplicateHeadersFlag = $duplicates;
        $this->headerRowNumber = $rowNumber;
        $headers = $this->readHeaderRow($rowNumber);

        $this->setColumnHeaders($headers);
    }

    /**
     * Rewind the file pointer
     *
     * If a header row has been set, the pointer is set just below the header
     * row. That way, when you iterate over the rows, that header row is
     * skipped.
     */
    public function rewind()
    {
        $this->file->rewind();
        if (null !== $this->headerRowNumber) {
            $this->file->seek($this->headerRowNumber + 1);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        if (null === $this->count) {
            $position = $this->key();

            $this->count = iterator_count($this);

            $this->seek($position);
        }

        return $this->count;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->file->next();
    }

    /**
     * {@inheritdoc}
     */
    public function valid(): bool
    {
        return $this->file->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->file->key();
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset)
    {
        $this->file->seek($offset);
    }

    /**
     * Get a row
     *
     * @param integer $number Row number
     *
     * @return array|null
     */
    public function getRow(int $number): ?array
    {
        $this->seek($number);

        return $this->current();
    }

    /**
     * Get rows that have an invalid number of columns
     *
     * @return array
     */
    public function getErrors(): array
    {
        if (0 === $this->key()) {
            // Iterator has not yet been processed, so do that now
            foreach ($this as $row) { /* noop */
            }
        }

        return $this->errors;
    }

    /**
     * Does the reader contain any invalid rows?
     *
     * @return boolean
     */
    public function hasErrors(): bool
    {
        return count($this->getErrors()) > 0;
    }

    /**
     * Should the reader use strict parsing?
     *
     * @return boolean
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * Set strict parsing
     *
     * @param boolean $strict
     */
    public function setStrict(bool $strict)
    {
        $this->strict = $strict;
    }

    /**
     * Read header row from CSV file
     *
     * @param integer $rowNumber Row number
     *
     * @return array
     *
     * @throws DuplicateHeadersException
     */
    protected function readHeaderRow(int $rowNumber): array
    {
        $this->file->seek($rowNumber);
        $headers = $this->file->current();

        // Test for duplicate column headers
        $diff = array_diff_assoc($headers, array_unique($headers));
        if (count($diff) > 0) {
            switch ($this->duplicateHeadersFlag) {
                case self::DUPLICATE_HEADERS_INCREMENT:
                    $headers = $this->incrementHeaders($headers);
                    break;
                case self::DUPLICATE_HEADERS_MERGE:
                    break;
                default:
                    throw new DuplicateHeadersException($diff);
            }
        }

        return $headers;
    }

    /**
     * Add an increment to duplicate headers
     *
     * So the following line:
     * |duplicate|duplicate|duplicate|
     * |first    |second   |third    |
     *
     * Yields value:
     * $duplicate => 'first', $duplicate1 => 'second', $duplicate2 => 'third'
     *
     * @param array $headers
     *
     * @return array
     */
    protected function incrementHeaders(array $headers): array
    {
        $incrementedHeaders = [];
        foreach (array_count_values($headers) as $header => $count) {
            $incrementedHeaders[] = $header;
            if ($count > 1) {
                for ($i = 1; $i < $count; $i++) {
                    $incrementedHeaders[] = $header.$i;
                }
            }
        }

        return $incrementedHeaders;
    }

    /**
     * Merges values for duplicate headers into an array
     *
     * So the following line:
     * |duplicate|duplicate|duplicate|
     * |first    |second   |third    |
     *
     * Yields value:
     * $duplicate => ['first', 'second', 'third']
     *
     * @param array $line
     *
     * @return array
     */
    protected function mergeDuplicates(array $line): array
    {
        $values = [];

        $i = 0;
        foreach ($this->columnHeaders as $count) {
            if (1 === $count) {
                $values[] = $line[$i];
            } else {
                $values[] = array_slice($line, $i, $count);
            }

            $i += $count;
        }

        return $values;
    }
}