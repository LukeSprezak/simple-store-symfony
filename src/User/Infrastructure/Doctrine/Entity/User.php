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
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[Entity]
#[UniqueEntity(fields: ['username'])]
#[UniqueEntity(fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[Id]
    #[Column(type: Types::GUID)]
    private string $id;

    #[Column(type: Types::STRING, length: 32, unique: true)]
    private string $username;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 254)]
    #[Column(type: Types::STRING, length: 254, unique: true)]
    private string $email;

    /** @var list<string> */
    #[Column(type: Types::JSON)]
    private array $roles = [];

    #[Ignore]
    #[Column]
    private string $password;

    #[Ignore]
    private ?string $plainPassword = null;

    #[Column(type: Types::BOOLEAN)]
    private bool $enabled;

    #[Ignore]
    #[Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $tokenHash = null;

    #[Ignore]
    #[Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $resetPasswordTokenHash = null;

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

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(#[\SensitiveParameter] ?string $plainPassword): self
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
        if ('' === $this->email) {
            throw new \LogicException('A user must have a non-empty email to authenticate.');
        }

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

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setToken(#[\SensitiveParameter] ?string $token): self
    {
        $this->tokenHash = null === $token || '' === $token ? null : hash('sha256', $token);

        return $this;
    }

    public function matchesToken(#[\SensitiveParameter] string $token): bool
    {
        return null !== $this->tokenHash && '' !== $token && hash_equals($this->tokenHash, hash('sha256', $token));
    }

    public function getResetPasswordTokenHash(): ?string
    {
        return $this->resetPasswordTokenHash;
    }

    public function setResetPasswordToken(#[\SensitiveParameter] ?string $resetPasswordToken): void
    {
        $this->resetPasswordTokenHash = null === $resetPasswordToken || '' === $resetPasswordToken ? null : hash('sha256', $resetPasswordToken);
    }

    public function matchesResetPasswordToken(#[\SensitiveParameter] string $resetPasswordToken): bool
    {
        return null !== $this->resetPasswordTokenHash && '' !== $resetPasswordToken && hash_equals($this->resetPasswordTokenHash, hash('sha256', $resetPasswordToken));
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
