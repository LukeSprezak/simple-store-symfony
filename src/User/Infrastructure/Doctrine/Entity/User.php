<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Doctrine\Entity;

use App\User\Domain\Enum\Role;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[Entity]
#[UniqueEntity(fields: ['username', 'email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[Id]
    #[Column(type: Types::GUID)]
    private string $id;

    #[Column(type: Types::STRING, length: 32, unique: true)]
    private string $username;

    #[Column(type: Types::STRING, length: 32, unique: true)]
    private string $email;

    #[Column(type: Types::JSON)]
    private array $roles = [];

    #[Column]
    private ?string $password;

    #[Column]
    private string $repeatPassword;

    #[Column(nullable: true)]
    private ?string $plainPassword = null;

    #[Column(type: Types::BOOLEAN)]
    private bool $enabled;

    #[Column(type: Types::STRING, nullable: true)]
    private ?string $token;

    #[Column(type: Types::STRING, nullable: true)]
    private ?string $resetPasswordToken;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastPasswordChange = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getRoles(): array
    {
        return array_unique([...$this->roles, Role::ROLE_USER->value]);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRepeatPassword(): string
    {
        return $this->repeatPassword;
    }

    public function setRepeatPassword(string $repeatPassword): self
    {
        $this->repeatPassword = $repeatPassword;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): void
    {
        $this->resetPasswordToken = $resetPasswordToken;
    }

    public function getLastPasswordChange(): ?\DateTimeImmutable
    {
        return $this->lastPasswordChange;
    }

    public function setLastPasswordChange(\DateTimeImmutable $lastPasswordChange): static
    {
        $this->lastPasswordChange = $lastPasswordChange;

        return $this;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
