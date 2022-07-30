<?php

declare(strict_types=1);

namespace App;

use DateTime;

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

        $charges = $stripe->getChargeIdsAfterDateWithoutSmartbillMeta($date_start);
        // Sort the charges alphabetically by their creation date (key).
        // This is required so that we can create the Smartbill invoices in
        // the correct chronological order in which the charges have been created.
        asort($charges);

        foreach ($charges as $timestamp => $charge_id) {
            $charge = $stripe->getChargeById($charge_id);
            $invoice = $stripe->getInvoiceById($charge['invoice']);
            $customer = $stripe->getCustomerById($charge['customer']);

            $smartbill = new Smartbill($this->settings['SMARTBILL_API_KEY']);
            $smartbill->createInvoice($invoice, $customer, $charge);
        }
        print_r($charges);
    }
}
