<?php

namespace Import\Reader;

use Import\Reader;

interface ReaderFactory
{
    public function getReader(mixed $value): Reader;
}
