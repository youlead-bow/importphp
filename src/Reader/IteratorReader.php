<?php

namespace Import\Reader;

use Import\Reader;
use IteratorIterator;

/**
 * Use an iterator as a reader
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class IteratorReader extends IteratorIterator implements Reader
{
}
