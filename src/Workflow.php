<?php

namespace Import;

/**
 * A mediator between a reader and one or more writers and converters
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
interface Workflow
{
    /**
     * Process the whole import workflow
     *
     * @throws Exception
     */
    public function process(): Result;
}
