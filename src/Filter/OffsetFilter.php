<?php

namespace Import\Filter;

/**
 * This filter can be used to filter out some items from the beginning and/or
 * end of the items.
 *
 * @author Ville Mattila <ville@eventio.fi>
 */
class OffsetFilter
{
    protected int $offset = 0;

    protected ?int $limit = null;

    protected int $offsetCount = 0;

    protected int $sliceCount = 0;

    protected bool $maxLimitHit = false;

    /**
     * @param integer $offset 0-based index of the item to start read from
     * @param integer|null $limit  Maximum count of items to read. null = no limit
     */
    public function __construct(int $offset = 0, ?int $limit = null)
    {
        $this->offset = $offset;
        $this->limit = $limit;
    }

    /**
     * {}
     */
    public function __invoke(array $item): bool
    {
        // In case we've already filtered up to limited
        if ($this->maxLimitHit) {
            return false;
        }

        $this->offsetCount++;

        // We have not reached the start offset
        if ($this->offsetCount < $this->offset + 1) {
            return false;
        }

        // There is no maximum limit, so we'll return always true
        if (null === $this->limit) {
            return true;
        }

        $this->sliceCount++;

        if ($this->sliceCount < $this->limit) {
            return true;
        } elseif ($this->sliceCount == $this->limit) {
            $this->maxLimitHit = true;

            return true;
        }

        return false;
    }
}
