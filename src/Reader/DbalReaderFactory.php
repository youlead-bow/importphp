<?php

namespace Import\Reader;

use Doctrine\DBAL\Connection;

class DbalReaderFactory implements ReaderFactory
{
    public function __construct(protected Connection $connection)
    {
    }

    public function getReader(mixed $value, array $params = []): DbalReader
    {
        return new DbalReader($this->connection, $value, $params);
    }
}
