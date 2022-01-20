<?php

namespace Import\ValueConverter;

use DateTimeInterface;
use Exception;
use Import\Exception\UnexpectedValueException;

/**
 * Convert a date string into another date string
 * E.g. You want to change the format of a string OR
 * If no output specified, return DateTime instance
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class DateTimeValueConverter
{
    /**
     * Date time format
     *
     * @see http://php.net/manual/en/datetime.createfromformat.php
     */
    protected ?string $inputFormat;

    /**
     * Date time format
     *
     * @see http://php.net/manual/en/datetime.createfromformat.php
     */
    protected ?string $outputFormat;
    private bool $immutable;

    /**
     * @param string|null $inputFormat
     * @param string|null $outputFormat
     */
    public function __construct(string $inputFormat = null, string $outputFormat = null, bool $immutable = true)
    {
        $this->inputFormat  = $inputFormat;
        $this->outputFormat = $outputFormat;
        $this->immutable = $immutable;
    }

    /**
     * Convert string to date time object then convert back to a string
     * using specified format
     *
     * If no output format specified then return
     * the \DateTime instance     *
     * @throws UnexpectedValueException|Exception
     */
    public function __invoke(mixed $input): DateTimeInterface|string|null
    {
        if (!$input) {
            return null;
        }

        /** @var DateTimeInterface $className */
        $className = $this->immutable ? 'DateTimeImmutable' : 'DateTime';

        if ($this->inputFormat) {
            $date = $className::createFromFormat($this->inputFormat, $input);
            if (false === $date) {
                throw new UnexpectedValueException(
                    $input . ' is not a valid date/time according to format ' . $this->inputFormat
                );
            }
        } else {
            $date = new $className($input);
        }

        if ($this->outputFormat) {
            return $date->format($this->outputFormat);
        }

        //if no output format specified we just return the \DateTime instance
        return $date;
    }
}
