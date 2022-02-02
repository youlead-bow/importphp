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
    private int $index = 0;

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
    public function process(mixed $item, int $index, callable $next): ?bool
    {
        if($this->writer instanceof Writer\IndexableWriter){
            $this->writer->setIndex($index);
        }

        $this->writer->writeItem($item);

        return $next($item, $index);
    }
}
