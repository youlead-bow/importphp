<?php

namespace Import\Writer;

/**
 * Class allowing multiple writers to write in same stream
 *
 * @author Benoît Burnichon <bburnichon@gmail.com>
 */
class StreamMergeWriter extends AbstractStreamWriter
{
    private string $discriminantField = 'discr';

    /**
     * @var AbstractStreamWriter[]
     */
    private array $writers = [];

    /**
     * Set discriminant field
     *
     * @param string $discriminantField
     *
     * @return $this
     */
    public function setDiscriminantField(string $discriminantField): static
    {
        $this->discriminantField = $discriminantField;

        return $this;
    }

    /**
     * Get discriminant Field
     *
     * @return string
     */
    public function getDiscriminantField(): string
    {
        return $this->discriminantField;
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item)
    {
        if ((isset($item[$this->discriminantField])
                || array_key_exists($this->discriminantField, $item))
            && $this->hasStreamWriter($key = $item[$this->discriminantField])
        ) {
            $writer = $this->getStreamWriter($key);

            $writer->writeItem($item);
        }
    }

    /**
     * Set stream writers
     *
     * @param AbstractStreamWriter[] $writers
     *
     * @return $this
     */
    public function setStreamWriters(array $writers): static
    {
        foreach ($writers as $key => $writer) {
            $this->setStreamWriter($key, $writer);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param AbstractStreamWriter $writer
     *
     * @return $this
     */
    public function setStreamWriter(string $key, AbstractStreamWriter $writer): static
    {
        $writer->setStream($this->getStream());
        $writer->setCloseStreamOnFinish(false);
        $this->writers[$key] = $writer;

        return $this;
    }

    /**
     * Get a previously registered Writer
     */
    public function getStreamWriter(string $key): AbstractStreamWriter
    {
        return $this->writers[$key];
    }

    /**
     * Get list of registered Writers
     *
     * @return AbstractStreamWriter[]
     */
    public function getStreamWriters(): array
    {
        return $this->writers;
    }

    /**
     * Is a writer registered for key?
     */
    public function hasStreamWriter(string $key): bool
    {
        return isset($this->writers[$key]);
    }

    /**
     * Set a stream
     *
     * @param resource $stream
     */
    public function setStream(mixed $stream): static
    {
        parent::setStream($stream);
        foreach ($this->getStreamWriters() as $writer) {
            $writer->setStream($stream);
        }

        return $this;
    }
}
