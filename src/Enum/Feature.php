<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Extensions that are only visible once an admin has set them up (konzept-5 §1, konzept-11 §2).
 */
enum Feature: string
{
    case Email = 'email';
    case Ki = 'ki';
}
