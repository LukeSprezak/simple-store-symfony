<?php

declare(strict_types=1);

namespace App\User\Domain\Model;

class User
{
    public function __construct(
        private string $id,
        private string $username,
        private string $email,
        private array $roles = ['ROLE_USER'],
    ) {
    }

    public static function create(string $id, string $name, string $email, array $roles = ['ROLE_USER']): self
    {
        return new self($id, $name, $email, $roles);
    }

    public static function fromPersistence(string $id, string $name, string $email, array $roles = ['ROLE_USER']): self
    {
        return new self($id, $name, $email, $roles);
    }

    private function setId(string $id): void
    {
        $this->id = $id;
    }

    private function setUsername(string $username): void
    {
        $this->username = $username;
    }

    private function setEmail(string $email): void
    {
        $this->email = $email;
    }

    private function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function addRole(string $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function removeRole(string $role): void
    {
        $this->roles = array_filter(
            $this->roles,
            static fn ($existingRole) => $existingRole !== $role
        );
    }
}
