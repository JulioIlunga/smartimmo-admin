<?php
namespace App\Security;

use App\Entity\User as User;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new CustomUserMessageAccountStatusException(
                'Vous n’avez pas les permissions nécessaires pour vous connecter.'
            );
        }

        if (!$user->isActive()) {
            // the message passed to this exception is meant to be displayed to the user
            throw new CustomUserMessageAccountStatusException('Veuillez contacter votre administrateur.');
        }
        if ($user->isBlock()) {
            // the message passed to this exception is meant to be displayed to the user
            throw new CustomUserMessageAccountStatusException('Veuillez contacter votre administrateur.');
        }

    }

    public function checkPostAuth(UserInterface $user): void
    {

        if (!$user instanceof User) {
            return;
        }

        // user account is expired, the user may be notified
        if (!$user->isActive()) {
            throw new AccountExpiredException('Veuillez contacter votre administrateur');
        }
        if ($user->isBlock()) {
            throw new AccountExpiredException('Veuillez contacter votre administrateur');
        }
    }
}
