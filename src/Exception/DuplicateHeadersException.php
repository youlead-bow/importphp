<?php

namespace Import\Exception;

use JetBrains\PhpStorm\Pure;

/**
 * @author David de Boer <david@ddeboer.nl>
 */
class DuplicateHeadersException extends ReaderException
{
    /**
     * @param array $duplicates
     */
    #[Pure]
    public function __construct(array $duplicates)
    {
        parent::__construct(sprintf('File contains duplicate headers: %s', implode(', ', $duplicates)));
    }
}
