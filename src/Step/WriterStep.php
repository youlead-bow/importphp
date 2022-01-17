<?php

namespace Import\Step;

use Import\Step;
use Import\Writer;

/**
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class WriterStep implements Step
{
    /**
     * @var Writer
     */
    private $writer;

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
    public function process($item, callable $next)
    {
        $this->writer->writeItem($item);

        return $next($item);
    }
}
