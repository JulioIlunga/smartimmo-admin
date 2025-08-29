<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints as Assert;
class PasswordRequirements extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Type('string'),
            new Assert\Length(['min' => 6]),
            new Assert\Regex([
                'pattern' => '/\d+/i',
            ]),
            new Assert\Regex([
                'pattern' => '/[#?!@$%^&*-]+/i',
            ]),
        ];
    }
}