<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;

class Stripe
{
    private const REQUEST_TIMEOUT = 10.0;
    private const MAX_ITEMS_PER_REQUEST = 100;

    private string $secret_key;

    public function __construct(string $secret_key)
    {
        $this->secret_key = $secret_key;
    }

    public function getChargeIdsAfterDateWithoutSmartbillMeta(DateTime $date_start): array
    {
        $stripe_charges = $this->getCharges($date_start);

        $charges = [];
        foreach ($stripe_charges as $charge) {
            if (
                $charge['status'] === 'succeeded'
                && empty($charge['metadata']['smartbill_invoice'])
            ) {
                $charges[$charge['created']] = $charge['id'];
            }
        }
        return $charges;
    }

    public function getChargeById(string $charge_id): array
    {
        return $this->sendGetRequest(sprintf(
            'v1/charges/%s',
            $charge_id
        ));
    }

    public function getInvoiceById(string $invoice_id): array
    {
        return $this->sendGetRequest(sprintf(
            'v1/invoices/%s',
            $invoice_id
        ));
    }

    public function getCustomerById(string $customer_id): array
    {
        return $this->sendGetRequest(sprintf(
            'v1/customers/%s',
            $customer_id
        ));
    }

    private function getCharges(DateTime $date_start): array
    {
        return $this->sendGetRequest('v1/charges', [
            'limit' => self::MAX_ITEMS_PER_REQUEST,
            'created[gte]' => $date_start->getTimestamp(),
        ])['data'];
    }

    private function sendGetRequest(string $path, array $parameters = []): array
    {
        $response = $this->getClient()->request('GET', $path, [
            'query' => $parameters,
            'auth' => [$this->secret_key, '']
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body;
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => 'https://api.stripe.com',
            'timeout'  => self::REQUEST_TIMEOUT,
        ]);
    }
}
