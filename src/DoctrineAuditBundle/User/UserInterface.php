<?php

namespace WithAlex\DoctrineAuditBundle\User;

interface UserInterface
{
    public function getId(): ?int;

    public function getUsername(): ?string;
}
