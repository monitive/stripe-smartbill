<?php

declare(strict_types=1);

namespace App;

use Composer\Script\Event;
use DateTime;
use Dotenv\Dotenv;

/**
 * The main execution entrypoint
 *
 * This file is called by the composer command line script.
 * It loads the configuration, settings and runs the invoice generator tool.
 */
class Main
{
    /**
     * Generate invoices for all charges created after the given date
     */
    public static function generate(Event $event): int
    {
        self::loadConfig();
        self::checkVatRatesFile();

        $arguments = $event->getArguments();
        if (empty($arguments)) {
            self::showHelpAndExit();
        }

        $start_date = self::getStartDate($arguments);

        $generate = new Generate($_ENV);
        $generate->run(new DateTime($start_date));

        return 0;
    }

    /**
     * Get the start date from the command line $arguments
     */
    private static function getStartDate(array $arguments): string
    {
        $date = DateTime::createFromFormat('Y-m-d', $arguments[0]);

        if ($date < '2020-01-01') {
            self::showHelpAndExit('Error: Start date must be after 2020-01-01');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Show script help info and exit
     */
    private static function showHelpAndExit(string $message = ''): void
    {
        echo "This script generates Smartbill invoices for Stripe charges after a given date.\n";
        echo "Usage: composer generate <start_date>\n";
        echo "Example: composer generate 2020-07-01\n";

        if (!empty($message)) {
            echo "\n$message\n";
        }

        exit(1);
    }

    /**
     * Load the configuration from the .env file
     */
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
     * Ensures there is a valid vat_rates.json file in the project root
     */
    private static function checkVatRatesFile(): void
    {
        if (!file_exists(__DIR__ . '/../vat_rates.json')) {
            throw new \Exception('vat_rates.json file not found. Check documentation and try again');
        }
    }
}
