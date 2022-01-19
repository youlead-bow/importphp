<?php

namespace Import;

use Closure;
use DateTime;
use Exception;
use Import\Step\PriorityStep;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Seld\Signal\SignalHandler;
use SplObjectStorage;

/**
 * A mediator between a reader and one or more writers and converters
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class StepAggregator implements Workflow, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private Reader $reader;

    /**
     * Identifier for the Import/Export
     */
    private ?string $name;

    private bool $skipItemOnFailure = false;

    private array $steps = [];

    private array $writers = [];

    /**
     * @param Reader $reader
     * @param string|null $name
     */
    #[Pure]
    public function __construct(Reader $reader, ?string $name = null)
    {
        $this->name = $name;
        $this->reader = $reader;

        // Defaults
        $this->logger = new NullLogger();
    }

    /**
     * Add a step to the current workflow
     *
     * @param Step         $step
     * @param integer|null $priority
     *
     * @return $this
     */
    public function addStep(Step $step, int $priority = null): static
    {
        $priority = null === $priority && $step instanceof PriorityStep ? $step->getPriority() : $priority;
        $priority = null === $priority ? 0 : $priority;

        $this->steps[$priority][] = $step;

        return $this;
    }

    /**
     * Add a new writer to the current workflow
     *
     * @param Writer $writer
     *
     * @return $this
     */
    public function addWriter(Writer $writer): static
    {
        $this->writers[] = $writer;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function process(): Result
    {
        $count      = 0;
        $exceptions = new SplObjectStorage();
        $startTime  = new DateTime;

        $signal = SignalHandler::create(['SIGTERM', 'SIGINT'], $this->logger);

        foreach ($this->writers as $writer) {
            $writer->prepare();
        }

        $pipeline = $this->buildPipeline();

        // Read all items
        foreach ($this->reader as $item) {
            try {
                if ($signal->isTriggered()) {
                    break;
                }

                if (false === $pipeline($item)) {
                    continue;
                }
            } catch(Exception $e) {
                if (!$this->skipItemOnFailure) {
                    throw $e;
                }

                $exceptions->attach($e, $index);
                $this->logger->error($e->getMessage());
            }

            $count++;
        }

        foreach ($this->writers as $writer) {
            $writer->finish();
        }

        return new Result($this->name, $startTime, new DateTime, $count, $exceptions);
    }

    /**
     * Sets the value which determines whether the item should be skipped when error occurs
     *
     * @param boolean $skipItemOnFailure When true skip current item on process exception and log the error
     *
     * @return $this
     */
    public function setSkipItemOnFailure(bool $skipItemOnFailure): static
    {
        $this->skipItemOnFailure = $skipItemOnFailure;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Builds the pipeline
     */
    private function buildPipeline(): callable|Closure
    {
        $nextCallable = function ($item) {
            // the final callable is a no-op
        };

        foreach ($this->getStepsSortedDescByPriority() as $step) {
            $nextCallable = function ($item) use ($step, $nextCallable) {
                return $step->process($item, $nextCallable);
            };
        }

        return $nextCallable;
    }

    /**
     * Sorts the internal list of steps and writers by priority in reverse order.
     */
    private function getStepsSortedDescByPriority(): array
    {
        $steps = $this->steps;
        // Use illogically large and small priorities
        $steps[-255][] = new Step\ArrayCheckStep;
        foreach ($this->writers as $writer) {
            $steps[-256][] = new Step\WriterStep($writer);
        }

        krsort($steps);

        $sortedStep = [];
        /** @var Step[] $stepsAtSamePriority */
        foreach ($steps as $stepsAtSamePriority) {
            foreach ($stepsAtSamePriority as $step) {
                $sortedStep[] = $step;
            }
        }

        return array_reverse($sortedStep);
    }
}
