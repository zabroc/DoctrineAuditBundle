<?php

namespace DH\DoctrineAuditBundle\Helper;

use DH\DoctrineAuditBundle\AuditConfiguration;
use DH\DoctrineAuditBundle\User\UserInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class AuditHelper
{
    /**
     * @var \DH\DoctrineAuditBundle\AuditConfiguration
     */
    private $configuration;

    /**
     * @param AuditConfiguration $configuration
     */
    public function __construct(AuditConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return \DH\DoctrineAuditBundle\AuditConfiguration
     */
    public function getConfiguration(): AuditConfiguration
    {
        return $this->configuration;
    }

    /**
     * Returns the primary key value of an entity.
     *
     * @param EntityManagerInterface $em
     * @param object                 $entity
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\Mapping\MappingException
     *
     * @return mixed
     */
    public function id(EntityManagerInterface $em, $entity)
    {
        /** @var ClassMetadata $meta */
        $meta = $em->getClassMetadata(\get_class($entity));
        $pk = $meta->getSingleIdentifierFieldName();

        if (isset($meta->fieldMappings[$pk])) {
            $type = Type::getType($meta->fieldMappings[$pk]['type']);

            return $this->value($em, $type, $meta->getReflectionProperty($pk)->getValue($entity));
        }

        /**
         * Primary key is not part of fieldMapping.
         *
         * @see https://github.com/DamienHarper/DoctrineAuditBundle/issues/40
         * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/tutorials/composite-primary-keys.html#identity-through-foreign-entities
         * We try to get it from associationMapping (will throw a MappingException if not available)
         */
        $targetEntity = $meta->getReflectionProperty($pk)->getValue($entity);

        $mapping = $meta->getAssociationMapping($pk);

        /** @var ClassMetadata $meta */
        $meta = $em->getClassMetadata($mapping['targetEntity']);
        $pk = $meta->getSingleIdentifierFieldName();
        $type = Type::getType($meta->fieldMappings[$pk]['type']);

        return $this->value($em, $type, $meta->getReflectionProperty($pk)->getValue($targetEntity));
    }

    /**
     * Computes a usable diff.
     *
     * @param EntityManagerInterface $em
     * @param object                 $entity
     * @param array                  $ch
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\Mapping\MappingException
     *
     * @return array
     */
    public function diff(EntityManagerInterface $em, $entity, array $ch): array
    {
        /** @var ClassMetadata $meta */
        $meta = $em->getClassMetadata(\get_class($entity));
        $diff = [];

        foreach ($ch as $fieldName => list($old, $new)) {
            $o = null;
            $n = null;

            if (
                $meta->hasField($fieldName) &&
                !isset($meta->embeddedClasses[$fieldName]) &&
                $this->configuration->isAuditedField($entity, $fieldName)
            ) {
                $mapping = $meta->fieldMappings[$fieldName];
                $type = Type::getType($mapping['type']);
                $o = $this->value($em, $type, $old);
                $n = $this->value($em, $type, $new);
            } elseif (
                $meta->hasAssociation($fieldName) &&
                $meta->isSingleValuedAssociation($fieldName) &&
                $this->configuration->isAuditedField($entity, $fieldName)
            ) {
                $o = $this->summarize($em, $old);
                $n = $this->summarize($em, $new);
            }

            if ($o !== $n) {
                $diff[$fieldName] = [
                    'old' => $o,
                    'new' => $n,
                ];
            }
        }
        ksort($diff);

        return $diff;
    }

    /**
     * Blames an audit operation.
     *
     * @return array
     */
    public function blame(): array
    {
        $user_type = null;

        $user = null === $this->configuration->getUserProvider() ? null : $this->configuration->getUserProvider()->getUser();

        $user_type = 'System';

        if ($user instanceof UserInterface) {
            $user_type = $user->getId() ? 'User' : 'Unknown User';
        }

        return [
            'user_type' => $user_type
        ];
    }

    /**
     * Returns an array describing an entity.
     *
     * @param EntityManagerInterface $em
     * @param object                 $entity
     * @param mixed                  $id
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\Mapping\MappingException
     *
     * @return array
     */
    public function summarize(EntityManagerInterface $em, $entity = null, $id = null): ?array
    {
        if (null === $entity) {
            return null;
        }

        $em->getUnitOfWork()->initializeObject($entity); // ensure that proxies are initialized
        /** @var ClassMetadata $meta */
        $meta = $em->getClassMetadata(\get_class($entity));
        $pkName = $meta->getSingleIdentifierFieldName();
        $pkValue = $id ?? $this->id($em, $entity);
        // An added guard for proxies that fail to initialize.
        if (null === $pkValue) {
            return null;
        }

        if (method_exists($entity, '__toString')) {
            $label = (string) $entity;
        } else {
            $label = \get_class($entity).'#'.$pkValue;
        }

        return [
            'label' => $label,
            'class' => $meta->name,
            'table' => $meta->getTableName(),
            $pkName => $pkValue,
        ];
    }

    /**
     * Return columns of audit tables.
     *
     * @return array
     */
    public function getAuditTableColumns(): array
    {
        return [
            'id' => [
                'type' => self::getDoctrineType('INTEGER'),
                'options' => [
                    'autoincrement' => true,
                    'unsigned' => true,
                ],
            ],
            'type' => [
                'type' => self::getDoctrineType('STRING'),
                'options' => [
                    'notnull' => true,
                    'length' => 10,
                ],
            ],
            'object_id' => [
                'type' => self::getDoctrineType('STRING'),
                'options' => [
                    'notnull' => true,
                ],
            ],
            'discriminator' => [
                'type' => self::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                ],
            ],
            'transaction_hash' => [
                'type' => self::getDoctrineType('STRING'),
                'options' => [
                    'notnull' => false,
                    'length' => 40,
                ],
            ],
            'diffs' => [
                'type' => self::getDoctrineType('JSON'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                ],
            ],
            'blame_user_type' => [
                'type' => self::getDoctrineType('STRING'),
                'options' => [
                    'default' => null,
                    'notnull' => false,
                    'length' => 255,
                ],
            ],
            'created_at' => [
                'type' => self::getDoctrineType('DATETIME_IMMUTABLE'),
                'options' => [
                    'notnull' => true,
                ],
            ],
        ];
    }

    public function getAuditTableIndices(string $tablename): array
    {
        return [
            'id' => [
                'type' => 'primary',
            ],
            'type' => [
                'type' => 'index',
                'name' => 'type_'.md5($tablename).'_idx',
            ],
            'object_id' => [
                'type' => 'index',
                'name' => 'object_id_'.md5($tablename).'_idx',
            ],
            'discriminator' => [
                'type' => 'index',
                'name' => 'discriminator_'.md5($tablename).'_idx',
            ],
            'transaction_hash' => [
                'type' => 'index',
                'name' => 'transaction_hash_'.md5($tablename).'_idx',
            ],
            'created_at' => [
                'type' => 'index',
                'name' => 'created_at_'.md5($tablename).'_idx',
            ],
        ];
    }

    public static function paramToNamespace(string $entity): string
    {
        return str_replace('-', '\\', $entity);
    }

    public static function namespaceToParam(string $entity): string
    {
        return str_replace('\\', '-', $entity);
    }

    /**
     * Type converts the input value and returns it.
     *
     * @param EntityManagerInterface $em
     * @param Type                   $type
     * @param mixed                  $value
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return mixed
     */
    private function value(EntityManagerInterface $em, Type $type, $value)
    {
        if (null === $value) {
            return null;
        }

        $platform = $em->getConnection()->getDatabasePlatform();

        switch ($type->getName()) {
            case self::getDoctrineType('BIGINT'):
                $convertedValue = (string) $value;

                break;
            case self::getDoctrineType('INTEGER'):
            case self::getDoctrineType('SMALLINT'):
                $convertedValue = (int) $value;

                break;
            case self::getDoctrineType('DECIMAL'):
            case self::getDoctrineType('FLOAT'):
            case self::getDoctrineType('BOOLEAN'):
                $convertedValue = $type->convertToPHPValue($value, $platform);

                break;
            default:
                $convertedValue = $type->convertToDatabaseValue($value, $platform);
        }

        return $convertedValue;
    }

    private static function getDoctrineType(string $type): string
    {
        return \constant((class_exists(Types::class, false) ? Types::class : Type::class).'::'.$type);
    }
}
