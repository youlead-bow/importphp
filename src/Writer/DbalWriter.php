<?php

declare(strict_types=1);


namespace Import\Writer;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Logging\SQLLogger;
use Import\Writer;
use InvalidArgumentException;
use RuntimeException;

class DbalWriter implements Writer, IndexableWriter
{
    protected bool $truncate = true;
    private ?SQLLogger $originalLogger;
    private ?string $query = null;
    private bool $debug = false;
    protected int $index = 0;
    protected int $batchSize = 5000;

    public function __construct(
        protected Connection $connection,
        protected string $table,
    )
    {}

    /**
     * @throws Exception
     */
    public function prepare()
    {
        if(!$this->debug) {
            $this->disableLogging();
        }

        if (true === $this->truncate) {
            $this->truncateTable();
        }

        $this->connection->beginTransaction();
    }

    /**
     * @param int $index
     */
    public function setIndex(int $index): void
    {
        $this->index = $index;
    }

    /**
     * @throws Exception
     */
    public function writeItem(array $item)
    {
        $this->loadQuery($item);
        if(is_null($this->query)){
            $this->connection->rollback();
            throw new RuntimeException('Unable to create the queryBuilder for ' . $this->table);
        }

        $aData = $this->loadQueryData($item);
        foreach ($aData as $data){
            $this->connection->executeStatement($this->query, $data);
            if($this->index % $this->batchSize === 0){
                $this->connection->commit();
                $this->connection->beginTransaction();
            }
        }
    }

    private function loadQuery(array $item)
    {
        $aFields = array_fill_keys(array_keys($item), '?');
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->insert($this->table)
                     ->values($aFields);
        $this->query = $queryBuilder->getSQL();
    }

    /**
     * @throws Exception
     */
    private function loadQueryData(array $item): array
    {
        $arrayItem = array_filter($item, 'is_array');
        if(empty($arrayItem)){
            return [array_values($item)];
        }
        if(count($arrayItem) > 1){
            $this->connection->rollback();
            throw new InvalidArgumentException('Invalid data because there is more than one array');
        }

        $fieldName = key($arrayItem);
        $values = current($arrayItem);

        $aData = [];
        foreach ($values as $value) {
            $data = $item;
            $data[$fieldName] = $value;
            $aData[] = array_values($data);
        }

        return $aData;
    }

    /**
     * @throws Exception
     */
    public function finish()
    {
        $this->connection->commit();

        if(!$this->debug) {
            $this->reEnableLogging();
        }
    }

    /**
     * @return boolean
     */
    public function getTruncate(): bool
    {
        return $this->truncate;
    }

    /**
     * Set whether to truncate the table first
     */
    public function setTruncate(bool $truncate): static
    {
        $this->truncate = $truncate;

        return $this;
    }

    /**
     * Disable truncation
     */
    public function disableTruncate(): static
    {
        $this->truncate = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * @param int $batchSize
     */
    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Truncate the database table for this writer
     * @throws Exception
     */
    protected function truncateTable()
    {
        $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS=0;');
        $query = $this->connection->getDatabasePlatform()->getTruncateTableSQL($this->table, true);
        $this->connection->executeQuery($query);
        $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Disable Doctrine logging
     */
    protected function disableLogging()
    {
        $config = $this->connection->getConfiguration();
        $this->originalLogger = $config->getSQLLogger();
        $config->setSQLLogger();
    }

    /**
     * Re-enable Doctrine logging
     */
    protected function reEnableLogging()
    {
        $config = $this->connection->getConfiguration();
        $config->setSQLLogger($this->originalLogger);
    }
}