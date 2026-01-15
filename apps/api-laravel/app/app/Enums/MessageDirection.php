<?php

namespace App\Enums;

enum MessageDirection: string
{
    case Inbound = 'in';
    case Outbound = 'out';
}
