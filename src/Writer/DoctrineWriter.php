<?php

namespace Import\Writer;

use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ObjectRepository;
use Import\Exception\UnsupportedDatabaseTypeException;
use Import\Writer;
use InvalidArgumentException;
use RuntimeException;

/**
 * A bulk Doctrine writer
 *
 * See also the {@link http://www.doctrine-project.org/docs/orm/2.1/en/reference/batch-processing.html Doctrine documentation}
 * on batch processing.
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class DoctrineWriter implements Writer, Writer\FlushableWriter
{
    /**
     * Doctrine object manager
     */
    protected ?EntityManagerInterface $entityManager;

    /**
     * Fully qualified model name
     */
    protected string $objectName;

    /**
     * Doctrine object repository
     */
    protected ObjectRepository $objectRepository;


    protected ClassMetadataInfo $objectMetadata;

    /**
     * Original Doctrine logger
     */
    protected SQLLogger $originalLogger;

    /**
     * Whether to truncate the table first
     */
    protected bool $disableAutoIncrement = true;

    /**
     * Whether to truncate the table first
     */
    protected bool $truncate = true;

    /**
     * List of fields used to look up an object
     */
    protected array $lookupFields = [];

    /**
     * Method used for looking up the item
     */
    protected array $lookupMethod;

    protected ?int $lastInsertedId = null;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $entityManager
     * @param string $objectName
     * @param array|string|null $index Field or fields to find current entities by
     * @param string $lookupMethod Method used for looking up the item
     * @throws UnsupportedDatabaseTypeException
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        string $objectName,
        array|string $index = null,
        string $lookupMethod = 'findOneBy'
    ) {
        $this->ensureSupportedEntityManager($entityManager);
        $this->entityManager = $entityManager;
        $this->objectRepository = $entityManager->getRepository($objectName);
        $this->objectMetadata = $entityManager->getClassMetadata($objectName);

        //translate objectName in case a namespace alias is used
        $this->objectName = $this->objectMetadata->getName();
        if ($index) {
            if (is_array($index)) {
                $this->lookupFields = $index;
            } else {
                $this->lookupFields = [$index];
            }
        }

        if (!method_exists($this->objectRepository, $lookupMethod)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Repository %s has no method %s',
                    get_class($this->objectRepository),
                    $lookupMethod
                )
            );
        }
        $this->lookupMethod = [$this->objectRepository, $lookupMethod];
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

    public function getDisableAutoIncrement(): bool
    {
        return $this->disableAutoIncrement;
    }

    public function setDisableAutoIncrement(bool $disableAutoIncrement): static
    {
        $this->disableAutoIncrement = $disableAutoIncrement;

        return $this;
    }


    /**
     * Disable Doctrine logging
     * @throws Exception
     */
    public function prepare()
    {
        $this->disableLogging();

        if (true === $this->disableAutoIncrement) {
            $this->disableAutoIncrement();
        }

        if (true === $this->truncate) {
            $this->truncateTable();
        }
    }

    /**
     * Re-enable Doctrine logging
     */
    public function finish()
    {
        $this->flush();
        $this->reEnableLogging();
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item)
    {
        $object = $this->findOrCreateItem($item);

        $this->loadAssociationObjectsToObject($item, $object);
        $this->updateObject($item, $object);

        $this->entityManager->persist($object);
    }

    /**
     * Flush and clear the object manager
     */
    public function flush()
    {
        $this->entityManager->flush();
        $this->entityManager->clear($this->objectName);
    }

    /**
     * Return the last inserted id
     * @return false|int|string
     * @throws Exception
     */
    public function getLastId(): false|int|string
    {
        if(empty($this->lookupFields) || count($this->lookupFields) > 1 || !$this->truncate){
            return false;
        }

        $tableName = $this->objectMetadata->table['name'];
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery('SELECT max('.current($this->lookupFields).') FROM '.$tableName);
        return current($result->fetchFirstColumn());
    }

    /**
     * Return a new instance of the object
     *
     * @return object
     */
    protected function getNewInstance(): object
    {
        $className = $this->objectMetadata->getName();

        if (class_exists($className) === false) {
            throw new RuntimeException('Unable to create new instance of ' . $className);
        }

        return new $className;
    }

    /**
     * Call a setter of the object
     */
    protected function setValue(object $object, mixed $value, string $setter)
    {
        if (method_exists($object, $setter)) {
            $object->$setter($value);
        }
    }

    protected function updateObject(array $item, object $object)
    {
        $inflector = InflectorFactory::createForLanguage(Language::FRENCH)->build();
        $fieldNames = $this->objectMetadata->getFieldNames();
        foreach ($fieldNames as $fieldName) {
            $value = null;
            $classifiedFieldName = $inflector->classify($fieldName);
            if (isset($item[$fieldName])) {
                $value = $item[$fieldName];
            }

            if (null === $value) {
                continue;
            }

            if (!($value instanceof DateTimeInterface)
                || $value != $this->objectMetadata->getFieldValue($object, $fieldName)
            ) {
                $setter = 'set' . $classifiedFieldName;
                $this->setValue($object, $value, $setter);
            }
        }
    }

    /**
     * Add the associated objects in case the item have for persist its relation
     */
    protected function loadAssociationObjectsToObject(array $item, object $object)
    {
        foreach ($this->objectMetadata->getAssociationMappings() as $associationMapping) {

            $value = null;
            if (isset($item[$associationMapping['fieldName']]) && !is_object($item[$associationMapping['fieldName']])) {
                $value = $this->entityManager->getReference($associationMapping['targetEntity'], $item[$associationMapping['fieldName']]);
            }

            if (null === $value) {
                continue;
            }

            $setter = 'set' . ucfirst($associationMapping['fieldName']);
            $this->setValue($object, $value, $setter);
        }
    }

    protected function disableAutoIncrement(){
        $this->objectMetadata->setIdGenerator(new AssignedGenerator());
        $this->objectMetadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
    }

    /**
     * Truncate the database table for this writer
     * @throws Exception
     */
    protected function truncateTable()
    {
        $tableName = $this->objectMetadata->table['name'];
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=0;');
        $query = $connection->getDatabasePlatform()->getTruncateTableSQL($tableName, true);
        $connection->executeQuery($query);
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Disable Doctrine logging
     */
    protected function disableLogging()
    {
        if (!($this->entityManager instanceof EntityManager)) return;

        $config = $this->entityManager->getConnection()->getConfiguration();
        $this->originalLogger = $config->getSQLLogger();
        $config->setSQLLogger();
    }

    /**
     * Re-enable Doctrine logging
     */
    protected function reEnableLogging()
    {
        if (!($this->entityManager instanceof EntityManager)) return;

        $config = $this->entityManager->getConnection()->getConfiguration();
        $config->setSQLLogger($this->originalLogger);
    }

    protected function findOrCreateItem(array $item): object
    {
        $object = null;
        // If the table was not truncated to begin with, find current object
        // first
        if (!$this->truncate) {
            if (!empty($this->lookupFields)) {
                $lookupConditions = array();
                foreach ($this->lookupFields as $fieldName) {
                    $lookupConditions[$fieldName] = $item[$fieldName];
                }

                $object = call_user_func($this->lookupMethod, $lookupConditions);
            } else {
                $object = $this->objectRepository->find(current($item));
            }
        }

        if (!$object) {
            return $this->getNewInstance();
        }

        return $object;
    }

    /**
     * @throws UnsupportedDatabaseTypeException
     */
    protected function ensureSupportedEntityManager(?EntityManager $entityManager)
    {
        if (!($entityManager instanceof EntityManager)) {
            throw new UnsupportedDatabaseTypeException($entityManager);
        }
    }
}