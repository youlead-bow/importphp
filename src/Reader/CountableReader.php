<?php

namespace Import\Reader;

use Countable;
use Import\Reader;

/**
 * Reader that provides the count of total items
 *
 * @author David de Boer <david@ddeboer.nl>
 */
interface CountableReader extends Reader, Countable
{
    // Don't add count() to interface: see https://github.com/ddeboer/data-import/pull/5
}
