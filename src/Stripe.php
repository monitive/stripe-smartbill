<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;

/**
 * Stripe Class
 *
 * Manages all requests to Stripe API.
 */
class Stripe
{
    private const REQUEST_TIMEOUT = 10.0;
    private const MAX_ITEMS_PER_REQUEST = 100;

    private string $secret_key;

    private Client $client;

    public function __construct(string $secret_key = '')
    {
        $this->secret_key = $secret_key;
        $this->client = new Client([
            'base_uri' => 'https://api.stripe.com',
            'timeout'  => self::REQUEST_TIMEOUT,
        ]);
    }

    /**
     * Retrieves Stripe Charges after a specific date and checks to see if they're
     * successfull and if they don't have the smartbill_invoice metadata, which indicates
     * that a Smartbill invoice has already been issued for that charge.
     *
     * @param DateTime $start_date Starting date to retrieve charges from.
     * @return array Array of Stripe Charges [timestamp => charge ID]
     */
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

    /**
     * Retrieve a Stripe Charge by its ID
     */
    public function getChargeById(string $charge_id): array
    {
        return $this->sendGetRequest(sprintf(
            'v1/charges/%s',
            $charge_id
        ));
    }

    /**
     * Retrieve a Stripe Invoice by its ID
     */
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

    /**
     * Get a list of Stripe charges after a specific date.
     */
    private function getCharges(DateTime $date_start): array
    {
        return $this->sendGetRequest('v1/charges', [
            'limit' => self::MAX_ITEMS_PER_REQUEST,
            'created[gte]' => $date_start->getTimestamp(),
        ])['data'];
    }

    /**
     * Update a specific Stripe Charge
     */
    public function updateCharge(string $charge_id, string $data): void
    {
        $this->sendPostRequest(sprintf(
            'v1/charges/%s',
            $charge_id
        ), $data);
    }

    /**
     * Set a Guzzle client to use for requests.
     * This is useful for testing purposes, to be able to inject an external
     * Client instance.
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Send a GET request to the Stripe API.
     */
    private function sendGetRequest(string $path, array $parameters = []): array
    {
        $response = $this->client->request('GET', $path, [
            'query' => $parameters,
            'auth' => [$this->secret_key, '']
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body;
    }

    /**
     * Send a POST request to the Stripe API.
     */
    private function sendPostRequest(string $path, string $body = ''): array
    {
        $response = $this->client->request('POST', $path, [
            'auth' => [$this->secret_key, ''],
            'body' => $body,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
