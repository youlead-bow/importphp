<?php

namespace Import\Step;

use Import\Step;
use Import\Writer;

/**
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class WriterStep implements Step
{
    private Writer $writer;

    /**
     * @param Writer $writer
     */
    public function __construct(Writer $writer)
    {
        $this->writer = $writer;
    }

    /**
     * {@inheritdoc}
     */
    public function process($item, callable $next): callable|bool
    {
        $this->writer->writeItem($item);

        return $next($item);
    }
}
