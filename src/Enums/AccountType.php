<?php

namespace Osaris\Monzo\Enums;

enum AccountType: string
{
    case Retail = 'uk_retail';
    case RetailJoint = 'uk_retail_joint';

    /**
     * Not in Monzo's docs, but business accounts are returned with this type
     * when /accounts is called without a filter.
     */
    case Business = 'uk_business';
}
