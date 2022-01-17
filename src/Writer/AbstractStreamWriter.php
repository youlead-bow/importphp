<?php

namespace Import\Writer;

use Import\Writer;
use InvalidArgumentException;

/**
 * Base class to write into streams
 *
 * @author Benoît Burnichon <bburnichon@gmail.com>
 */
abstract class AbstractStreamWriter implements Writer
{
    use WriterTemplate;

    private mixed $stream;
    private bool $closeStreamOnFinish = true;

    /**
     * @param resource $stream
     */
    public function __construct(mixed $stream = null)
    {
        if (null !== $stream) {
            $this->setStream($stream);
        }
    }

    /**
     * Set the stream resource
     * @throws InvalidArgumentException
     */
    public function setStream($stream): static
    {
        if (! is_resource($stream) || ! 'stream' == get_resource_type($stream)) {
            throw new InvalidArgumentException(sprintf(
                'Expects argument to be a stream resource, got %s',
                is_resource($stream) ? get_resource_type($stream) : gettype($stream)
            ));
        }

        $this->stream = $stream;

        return $this;
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        if (null === $this->stream) {
            $this->setStream(fopen('php://temp', 'rb+'));
            $this->setCloseStreamOnFinish(false);
        }

        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function finish()
    {
        if (is_resource($this->stream) && $this->getCloseStreamOnFinish()) {
            fclose($this->stream);
        }
    }

    /**
     * Should underlying stream be closed on finish?
     */
    public function setCloseStreamOnFinish(bool $closeStreamOnFinish = true): static
    {
        $this->closeStreamOnFinish = $closeStreamOnFinish;

        return $this;
    }

    /**
     * Is Stream closed on finish?
     */
    public function getCloseStreamOnFinish(): bool
    {
        return $this->closeStreamOnFinish;
    }
}
