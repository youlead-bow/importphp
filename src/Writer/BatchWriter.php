<?php

namespace Import\Writer;

use Import\Writer;
use SplDoublyLinkedList;
use SplQueue;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class BatchWriter implements Writer
{
    private Writer $delegate;
    private int $size;
    private SplQueue $queue;

    /**
     * @param Writer  $delegate
     * @param integer $size
     */
    public function __construct(Writer $delegate, int $size = 20)
    {
        $this->delegate = $delegate;
        $this->size = $size;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(): void
    {
        $this->delegate->prepare();

        $this->queue = new SplQueue();
        $this->queue->setIteratorMode(SplDoublyLinkedList::IT_MODE_DELETE);
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item): void
    {
        $this->queue->push($item);

        if (count($this->queue) >= $this->size) {
            $this->flush();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finish(): void
    {
        $this->flush();

        $this->delegate->finish();
    }

    /**
     * Flush the internal buffer to the delegated writer
     */
    private function flush(): void
    {
        foreach ($this->queue as $item) {
            $this->delegate->writeItem($item);
        }

        if ($this->delegate instanceof FlushableWriter) {
            $this->delegate->flush();
        }
    }
}
