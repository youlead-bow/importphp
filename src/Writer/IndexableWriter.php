<?php

declare(strict_types=1);


namespace Import\Writer;

use Import\Writer;

interface IndexableWriter extends Writer
{
    public function setIndex(int $index): void;
}
