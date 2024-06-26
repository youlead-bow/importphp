<?php

namespace Import\Writer;

use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
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
class DoctrineWriter implements Writer, FlushableWriter, IndexableWriter
{
    const AUTO_INCREMENT_ENABLE = 1;
    const AUTO_INCREMENT_DISABLE = 0;
    const AUTO_INCREMENT_DEFAUT = -1;

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


    protected ClassMetadata $objectMetadata;


    /**
     * AutoIncrement management
     */
    protected int $autoIncrement = self::AUTO_INCREMENT_DEFAUT;

    /**
     * Whether to truncate the table first
     */
    protected bool $truncate = true;

    /**
     * @var int Batch size
     */
    protected int $batchSize = 50000;

    /**
     * @var int Item index
     */
    protected int $index = 0;

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
     * @param int $batchSize
     */
    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Disable truncation
     */
    public function disableTruncate(): static
    {
        $this->truncate = false;

        return $this;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function enableAutoIncrement(): void
    {
        $this->autoIncrement = self::AUTO_INCREMENT_ENABLE;
    }

    public function disableAutoIncrement(): void
    {
        $this->autoIncrement = self::AUTO_INCREMENT_DISABLE;
    }


    /**
     * Disable Doctrine logging
     * @throws Exception
     */
    public function prepare(): void
    {
        if (true === $this->truncate) {
            $this->truncateTable();
        }

        $this->setAutoIncrement();
    }

    /**
     * Re-enable Doctrine logging
     */
    public function finish(): void
    {
        $this->flush();
    }

    /**
     * @param int $index
     */
    public function setIndex(int $index): void
    {
        $this->index = $index;
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item): void
    {
        $object = $this->findOrCreateItem($item);

        $this->loadAssociationObjectsToObject($item, $object);
        $this->updateObject($item, $object);

        $this->entityManager->persist($object);
        if($this->index % $this->batchSize === 0){
            $this->flush();
        }
    }

    /**
     * Flush and clear the object manager
     */
    public function flush(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Return the last inserted id
     * @return false|int|string
     * @throws Exception
     */
    public function getLastId(): false|int|string
    {
        $fieldId = !empty($this->lookupFields) ? current($this->lookupFields) : 'id';
        $tableName = $this->objectMetadata->table['name'];
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery('SELECT max('.$fieldId.') FROM '.$tableName);
        $lastId = current($result->fetchFirstColumn());
        return !empty($lastId) ? $lastId : 1;
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
    protected function setValue(object $object, mixed $value, string $setter): void
    {
        if (method_exists($object, $setter)) {
            $object->$setter($value);
        }
    }

    protected function updateObject(array $item, object $object): void
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
     * @throws ORMException
     */
    protected function loadAssociationObjectsToObject(array $item, object $object): void
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

    protected function setAutoIncrement(): static
    {
        $this->objectMetadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        if($this->autoIncrement == self::AUTO_INCREMENT_ENABLE){
            $this->objectMetadata->setIdGenerator(new IdentityGenerator());
        } else {
            $this->objectMetadata->setIdGenerator(new AssignedGenerator());
        }

        return $this;
    }


    /**
     * Truncate the database table for this writer
     * @throws Exception
     */
    protected function truncateTable(): void
    {
        $tableName = $this->objectMetadata->table['name'];
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=0;');
        $query = $connection->getDatabasePlatform()->getTruncateTableSQL($tableName, true);
        $connection->executeQuery($query);
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
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
            }
            /*else {
                $object = $this->objectRepository->find(current($item));
            }*/
        }

        if (!$object) {
            return $this->getNewInstance();
        }

        return $object;
    }

    /**
     * @throws UnsupportedDatabaseTypeException
     */
    protected function ensureSupportedEntityManager(?EntityManager $entityManager): void
    {
        if (!($entityManager instanceof EntityManager)) {
            throw new UnsupportedDatabaseTypeException($entityManager);
        }
    }
}