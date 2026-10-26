<?php
namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class Account implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(public readonly string $id, public readonly bool $admin, private string $password, public readonly array $profile = []) {}
    public function getUserIdentifier(): string { return ($this->admin ? 'admin:' : 'user:').$this->id; }
    public function getRoles(): array { return [$this->admin ? 'ROLE_ADMIN' : 'ROLE_USER']; }
    public function getPassword(): string { return $this->password; }
    public function eraseCredentials(): void {}
}
