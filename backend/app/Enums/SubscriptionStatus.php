<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Free = 'free';
    case Subscribed = 'subscribed';
}
