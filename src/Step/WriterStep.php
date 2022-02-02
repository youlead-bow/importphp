<?php

namespace Import\Step;

use Import\Step;
use Import\Writer;

/**
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class WriterStep implements Step, IndexStep
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
    public function process(mixed $item, callable $next): ?bool
    {
        if($this->writer instanceof Writer\IndexableWriter){
            $this->writer->setIndex($this->index);
        }

        $this->writer->writeItem($item);

        return $next($item);
    }

    public function setIndex(int $index): void
    {
        $this->index = $index;
    }
}
