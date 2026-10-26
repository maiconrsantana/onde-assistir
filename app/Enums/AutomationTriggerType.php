<?php

namespace App\Enums;

enum AutomationTriggerType: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
}
