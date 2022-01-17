<?php

namespace Import;

use DateInterval;
use DateTime;
use SplObjectStorage;

/**
 * Simple Container for Workflow Results
 *
 * @author Aydin Hassan <aydin@hotmail.co.uk>
 */
class Result
{
    /**
     * Identifier given to the import/export
     */
    protected string $name;

    protected DateTime $startTime;

    protected DateTime $endTime;

    protected DateInterval $elapsed;

    protected int $errorCount = 0;

    protected int $successCount = 0;

    protected int $totalProcessedCount = 0;

    protected SplObjectStorage $exceptions;

    /**
     * @param string $name
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @param integer $totalCount
     * @param SplObjectStorage $exceptions
     */
    public function __construct(string $name, DateTime $startTime, DateTime $endTime, int $totalCount, SplObjectStorage $exceptions)
    {
        $this->name                = $name;
        $this->startTime           = $startTime;
        $this->endTime             = $endTime;
        $this->elapsed             = $startTime->diff($endTime);
        $this->totalProcessedCount = $totalCount;
        $this->errorCount          = count($exceptions);
        $this->successCount        = $totalCount - $this->errorCount;
        $this->exceptions          = $exceptions;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartTime(): DateTime
    {
        return $this->startTime;
    }

    public function getEndTime(): DateTime
    {
        return $this->endTime;
    }

    public function getElapsed(): DateInterval
    {
        return $this->elapsed;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getTotalProcessedCount(): int
    {
        return $this->totalProcessedCount;
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    public function getExceptions(): SplObjectStorage
    {
        return $this->exceptions;
    }
}
