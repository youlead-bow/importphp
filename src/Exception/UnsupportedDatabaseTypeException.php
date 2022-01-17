<?php

namespace Import\Exception;

use Import\Exception;
use JetBrains\PhpStorm\Pure;

class UnsupportedDatabaseTypeException extends \Exception implements Exception
{
    #[Pure]
    public function __construct($objectManager)
    {
        $message = sprintf(
            'Unknown Object Manager type. Expected \Doctrine\ORM\EntityManager or \Doctrine\ODM\MongoDB\DocumentManager, %s given',
            get_class($objectManager)
        );

        parent::__construct($message);
    }
}
