<?php

// src/Service/CreditWalletManager.php
namespace App\Service;

use App\Entity\CreditWallet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CreditWalletManager
{
    public function __construct(private EntityManagerInterface $em) {}

    public function ensureWallet(User $user): CreditWallet
    {
        $wallet = $user->getCreditWallet();
        if (!$wallet) {
            $wallet = new CreditWallet();
            $wallet->setBalanceCredits(0);
            $wallet->setLastRefillAt(new \DateTime('now'));
            $this->em->persist($wallet);
            $user->setCreditWallet($wallet);
            $this->em->flush();
        }
        return $wallet;
    }

    public function addCredits(CreditWallet $wallet, int $amount): void
    {
        $wallet->setBalanceCredits(max(0, $wallet->getBalanceCredits() + $amount));
        $this->em->flush();
    }
    public function subCredits(CreditWallet $wallet, int $amount): void
    {
        $wallet->setBalanceCredits(max(0, $wallet->getBalanceCredits() - $amount));
        $this->em->flush();
    }
}
