<?php

namespace Import\Step;

use Import\Step;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
interface IndexStep extends Step
{
    public function setIndex(int $index): void;
}
