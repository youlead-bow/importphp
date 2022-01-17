<?php

namespace Import\ValueConverter;

use Import\Exception\UnexpectedValueException;

/**
 * Convert an date time object into string
 */
class DateTimeToStringValueConverter
{
    /**
     * Date time format
     *
     * @see http://php.net/manual/en/datetime.createfromformat.php
     */
    protected string $outputFormat;

    /**
     * @param string $outputFormat
     */
    public function __construct(string $outputFormat = 'Y-m-d H:i:s')
    {
        $this->outputFormat = $outputFormat;
    }

    /**
     * Convert string to date time object
     * using specified format
     * @throws UnexpectedValueException
     */
    public function __invoke(mixed $input): \DateTime|string|null
    {
        if (!$input) {
            return null;
        }

        if (!($input instanceof \DateTime)) {
            throw new UnexpectedValueException('Input must be DateTime object.');
        }

        return $input->format($this->outputFormat);
    }
}
