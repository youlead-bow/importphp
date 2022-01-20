<?php

namespace Import\Step;

use Import\Step;
use SplPriorityQueue;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class FilterStep implements CountableStep
{
    private SplPriorityQueue $filters;

    public function __construct()
    {
        $this->filters = new SplPriorityQueue();
    }

    /**
     * @param callable $filter
     * @param null $priority
     * @return FilterStep
     */
    public function add(callable $filter, $priority = null): static
    {
        $this->filters->insert($filter, $priority);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function process(mixed $item, callable $next): ?bool
    {
        foreach (clone $this->filters as $filter) {
            if (false === call_user_func($filter, $item)) {
                return false;
            }
        }

        return $next($item);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->filters->count();
    }
}
