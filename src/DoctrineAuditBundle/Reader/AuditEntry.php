<?php

namespace DH\DoctrineAuditBundle\Reader;

class AuditEntry
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $object_id;

    /**
     * @var null|string
     */
    protected $transaction_hash;

    /**
     * @var string
     */
    protected $diffs;

    /**
     * @var string
     */
    protected $blame_user_type;

    /**
     * @var string
     */
    protected $created_at;

    /**
     * @param string          $name
     * @param null|int|string $value
     */
    public function __set(string $name, $value): void
    {
        $this->{$name} = $value;
    }

    /**
     * @param string $name
     *
     * @return null|int|string
     */
    public function __get(string $name)
    {
        return $this->{$name};
    }

    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }

    /**
     * Get the value of id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the value of object_id.
     *
     * @return string
     */
    public function getObjectId(): string
    {
        return $this->object_id;
    }

    /**
     * Get the value of transaction_hash.
     *
     * @return null|string
     */
    public function getTransactionHash(): ?string
    {
        return $this->transaction_hash;
    }

    /**
     * Get the value of blame_user.
     *
     * @return null|string
     */
    public function getUserType(): ?string
    {
        return $this->blame_user_type;
    }

    /**
     * Get the value of created_at.
     *
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    /**
     * Get the value of created_at.
     *
     * @return array
     */
    public function getDiffs(): ?array
    {
        return json_decode($this->diffs, true);
    }
}
