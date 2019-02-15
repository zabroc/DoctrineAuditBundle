<?php

namespace WithAlex\DoctrineAuditBundle\User;

interface UserProviderInterface
{
    public function getUser(): ?UserInterface;
}
