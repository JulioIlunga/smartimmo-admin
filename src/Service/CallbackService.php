<?php
// src/Service/LeadService.php

namespace App\Service;

use App\Entity\Preference;
use App\Entity\User;
use App\Repository\LeadClaimsRepository;

final class CallbackService
{
    public function __construct(
        private readonly LeadClaimsRepository $leadClaimRepository
    ) {}

    /**
     * Returns true if the given user has an active/valid claim on the lead.
     */
    public function isClaimedByUser(Preference $lead, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Optional fast-path: if the lead is assigned directly to the user
        if (method_exists($lead, 'getAssignedTo') && $lead->getAssignedTo() instanceof User) {
            if ($lead->getAssignedTo()->getId() === $user->getId()) {
                return true;
            }
        }

        // Check a claim record in DB
        return $this->leadClaimRepository->userHasClaimOnLead($user, $lead);
    }
}
