<?php

namespace WithAlex\DoctrineAuditBundle;

/**
 * Class AuditConfiguration.
 */
class AuditConfiguration
{
    /**
     * @var string
     */
    private $tablePrefix;

    /**
     * @var string
     */
    private $tableSuffix;

    /**
     * @var array
     */
    private $ignoredEntities = [];

    /**
     * @var array
     */
    private $ignoredColumns = [];

    public function __construct(array $config)
    {
        $this->tablePrefix = $config['table_prefix'];
        $this->tableSuffix = $config['table_suffix'];
        $this->ignoredEntities = $config['ignored_entities'];

        if (isset($config['ignored_columns']) && !empty($config['ignored_columns'])) {
            // use entity names as array keys for easier lookup
            foreach ($config['ignored_columns'] as $column => $entities) {
                $this->ignoredColumns[$column] = $entities;
            }
        }
    }

    /**
     * Returns true if $entity is audited.
     *
     * @param $entity
     *
     * @return bool
     */
    public function isAudited($entity): bool
    {
        if (!empty($this->ignoredEntities)) {
            foreach ($this->ignoredEntities as $ignoredEntity) {
                if (\is_object($entity) && $entity instanceof $ignoredEntity && !is_subclass_of($entity, $ignoredEntity)) {
                    return false;
                }
                if (\is_string($entity) && $entity === $ignoredEntity) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns true if $field is audited.
     *
     * @param $entity
     * @param $field
     *
     * @return bool
     */
    public function isAuditedField($entity, $field): bool
    {
        if ($this->isAudited($entity)) {
            return !(\array_key_exists($field, $this->ignoredColumns) &&
                \in_array($entity, $this->ignoredColumns[$field]['entities']))
            ;
        }

        return false;
    }

    /**
     * Get the value of tablePrefix.
     *
     * @return string
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Get the value of tableSuffix.
     *
     * @return string
     */
    public function getTableSuffix(): string
    {
        return $this->tableSuffix;
    }

    /**
     * Get the value of excludedColumns.
     *
     * @return array
     */
    public function getIgnoredEntities(): array
    {
        return $this->ignoredEntities;
    }

    /**
     * Get the value of entities.
     *
     * @return array
     */
    public function getIgnoredColumns(): array
    {
        return $this->ignoredColumns;
    }
}
