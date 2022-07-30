<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;

class Generate
{
    private array $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function run(DateTime $date_start): void
    {
        $stripe = new Stripe($this->settings['STRIPE_SECRET_KEY']);
        $charges = $stripe->getChargeIdsAfterDate($date_start);
        print_r($charges);
    }
}
