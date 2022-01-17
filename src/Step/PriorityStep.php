<?php

namespace Import\Step;

use Import\Step;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
interface PriorityStep extends Step
{
    public function getPriority(): int;
}
