<?php

namespace Import\Reader;

use Import\Reader;

/**
 * Factory interface to create file based readers
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
interface ReaderFactory
{
    /**
     * @param \SplFileObject $file
     *
     * @return Reader
     */
    public function getReader(\SplFileObject $file);
}
