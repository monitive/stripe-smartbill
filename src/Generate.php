<?php

declare(strict_types=1);

namespace App;

use DateTime;

class Generate
{
    private array $settings;
    private Logger $logger;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->logger = new Logger();
    }

    public function run(DateTime $date_start): void
    {
        $stripe = new Stripe($this->settings['STRIPE_SECRET_KEY']);

        $charges = $stripe->getChargeIdsAfterDateWithoutSmartbillMeta($date_start);
        // Sort the charges alphabetically by their creation date (key).
        // This is required so that we can create the Smartbill invoices in
        // the correct chronological order in which the charges have been created.
        asort($charges);

        $this->logger->log('Found ' . count($charges) . ' charges to process...');

        $smartbill = new Smartbill(
            $this->settings,
            json_decode(file_get_contents(__DIR__ . '/../vat_rates.json'), true)
        );

        foreach ($charges as $timestamp => $charge_id) {
            // Get the charge details from Stripe.
            $charge = $stripe->getChargeById($charge_id);
            $invoice = $stripe->getInvoiceById($charge['invoice']);
            // $customer = $stripe->getCustomerById($charge['customer']);

            $this->logger->log(sprintf(
                'Stripe charge %s created %s value %s %.2f',
                $charge_id,
                DateTime::createFromFormat('U', (string)$timestamp)->format('Y-m-d H:i:s'),
                strtoupper($charge['currency']),
                $charge['amount'] / 100
            ));

            // Create the Smartbill invoice.
            $smartbill_id = $smartbill->createInvoice($invoice, $charge);

            $this->logger->log(sprintf(
                'Created Smartbill invoice %s%s',
                $smartbill_id['series'],
                $smartbill_id['number']
            ));

            // Check created invoice value vs payment value
            $smartbill_payment = $smartbill->getPayment($smartbill_id['number']);
            if ($smartbill_payment['invoiceTotalAmount'] !== $smartbill_payment['paidAmount']) {
                throw new \Exception(sprintf(
                    'Invoice %s%s value mismatch, Stripe invoice %s value %.2f, Smartbill invoice value %.2f.'
                        . ' Please check the invoices.',
                    $smartbill_id['series'],
                    $smartbill_id['number'],
                    $invoice['number'],
                    $smartbill_payment['paidAmount'],
                    $smartbill_payment['invoiceTotalAmount']
                ));
            }

            // Update the charge with the Smartbill invoice ID.
            //TODO: $stripe->updateChargeMeta('smartbill_invoice', $smartbill_id);
        }
    }
}
