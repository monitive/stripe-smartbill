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
        $dotenv->required(['STRIPE_SECRET_KEY']);
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
