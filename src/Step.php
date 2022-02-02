<?php

namespace Import;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
interface Step
{
    /**
     * Any processing done on each item in the data stack
     *
     * @param mixed    $item
     * @param callable $next
     *
     */
    public function process(mixed $item, int $index, callable $next): ?bool;
}
