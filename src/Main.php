<?php

declare(strict_types=1);

namespace App;

use DateTime;
use Dotenv\Dotenv;
use GuzzleHttp\Client;

class Main
{
    private static function loadConfig(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        $dotenv->required([
            'STRIPE_SECRET_KEY',
            'SMARTBILL_API_KEY',
            'SMARTBILL_SERIES',
            'SMARTBILL_COMPANY_CUI'
        ]);
    }

    /**
     * Genereaza facturi Smartbill pe baza platilor din Stripe
     */
    public static function generate(): int
    {
        self::loadConfig();

        $generate = new Generate($_ENV);
        $generate->run(new DateTime('2022-07-01'));

        return 0;
    }
}
