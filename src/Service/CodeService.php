<?php

namespace App\Service;

class CodeService
{
    // This function will return a random
    // string of specified length
    function ConfirmationToken($length_of_integer): string
    {
        // String of all alphanumeric character
        $str_result = '0123456789';

        // Shuffle the $str_result and returns substring
        // of specified length
        return substr(str_shuffle($str_result),
            0, $length_of_integer);
    }
}