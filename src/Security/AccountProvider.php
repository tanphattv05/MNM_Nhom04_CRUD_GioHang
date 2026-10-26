<?php
namespace App\Security;

use App\Repository\AccountRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

final class AccountProvider implements UserProviderInterface
{
    public function __construct(private AccountRepository $accounts) {}
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Sessions retain the immutable integer customer ID. Login usernames
        // are resolved by LoginAuthenticator; renaming never changes ownership.
        [$kind, $id] = array_pad(explode(':', $identifier, 2), 2, '');
        $user = in_array($kind, ['admin','user'], true) ? $this->accounts->byId($id, $kind === 'admin') : null;
        if (!$user) { throw new UserNotFoundException(); }
        return $user;
    }
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Account) { throw new UnsupportedUserException(); }
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }
    public function supportsClass(string $class): bool { return $class === Account::class; }
}
