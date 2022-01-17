<?php

namespace Import\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;

/**
 * Reads data through the Doctrine DBAL
 */
class DbalReader implements CountableReader
{
    private Connection $connection;
    private ?array $data;
    private ?Statement $stmt;
    private ?Result $result;
    private string $sql;
    private array $params;
    private ?int $rowCount;
    private bool $rowCountCalculated = true;
    private ?int $key;

    /**
     * @param Connection $connection
     * @param string $sql
     * @param array      $params
     */
    public function __construct(Connection $connection, string $sql, array $params = [])
    {
        $this->connection = $connection;

        $this->setSql($sql, $params);
    }

    /**
     * Do calculate row count?
     *
     * @param boolean $calculate
     */
    public function setRowCountCalculated(bool $calculate = true)
    {
        $this->rowCountCalculated = $calculate;
    }

    /**
     * Is row count calculated?
     *
     * @return boolean
     */
    public function isRowCountCalculated(): bool
    {
        return $this->rowCountCalculated;
    }

    /**
     * Set Query string with Parameters
     */
    public function setSql(string $sql, array $params = [])
    {
        $this->sql = $sql;

        $this->setSqlParameters($params);
    }

    /**
     * Set SQL parameters
     */
    public function setSqlParameters(array $params)
    {
        $this->params = $params;

        $this->stmt = null;
        $this->result = null;
        $this->rowCount = null;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function current(): array|null
    {
        if (is_null($this->data)) {
            $this->rewind();
        }

        return $this->data;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function next()
    {
        $this->key++;
        $this->data = $this->result->fetchAssociative();
    }

    /**
     * {@inheritdoc}
     */
    public function key(): int
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function valid(): bool
    {
        if (null === $this->data) {
            $this->rewind();
        }

        return (false !== $this->data);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function rewind()
    {
        if (null === $this->stmt) {
            $this->stmt = $this->prepare($this->sql, $this->params);
        }
        if (0 !== $this->key) {
            $this->result = $this->stmt->executeQuery();
            $this->data = $this->result->fetchAssociative();
            $this->key = 0;
        }
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function count(): ?int
    {
        if (null === $this->rowCount) {
            if ($this->rowCountCalculated) {
                $this->doCalcRowCount();
            } else {
                if (null === $this->stmt) {
                    $this->rewind();
                }
                $this->rowCount = $this->result->rowCount();
            }
        }

        return $this->rowCount;
    }

    /**
     * @throws Exception
     */
    private function doCalcRowCount()
    {
        $statement = $this->prepare(sprintf('SELECT COUNT(*) FROM (%s) AS port_cnt', $this->sql), $this->params);
        $result = $statement->executeQuery();

        $this->rowCount = (int) $result->fetchFirstColumn();
    }

    /**
     * Prepare given statement
     * @throws Exception
     */
    private function prepare(string $sql, array $params): Statement
    {
        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        return $statement;
    }
}
