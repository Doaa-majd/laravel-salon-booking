<?php

namespace App\Enums;

enum ReminderChannel: string
{
    case Whatsapp = 'whatsapp';
    case Email = 'email';
}
