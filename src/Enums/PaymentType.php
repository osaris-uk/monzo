<?php

namespace Osaris\Monzo\Enums;

/**
 * Receipt payment types.
 */
enum PaymentType: string
{
    case Card = 'card';
    case Cash = 'cash';
    case GiftCard = 'gift_card';
}
