<?php

namespace Import\Writer;

use Import\Writer;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
interface FlushableWriter extends Writer
{
    /**
     * Flush the output buffer
     */
    public function flush();
}
