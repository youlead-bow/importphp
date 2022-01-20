<?php

namespace Import\Step;

use Import\Step;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
interface CountableStep extends Step
{
    /**
     * Count number of element in the step
     */
    public function count(): int;
}
