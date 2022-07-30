<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;

class Stripe
{
    private string $secret_key;

    public function __construct(string $secret_key)
    {
        $this->secret_key = $secret_key;
    }

    public function getChargeIdsAfterDate(DateTime $date_start): array
    {
        $stripe_charges = $this->getCharges($date_start);

        $charges = [];
        foreach ($stripe_charges as $charge) {
            $charges[] = $charge['id'];
        }
        return $charges;
    }

    private function getCharges(DateTime $date_start): array
    {
        return $this->sendGetRequest('v1/charges', [
            'limit' => 100,
            'created[gte]' => $date_start->getTimestamp(),
        ]);
    }

    private function sendGetRequest(string $path, array $parameters = []): array
    {
        $response = $this->getClient()->request('GET', $path, [
            'query' => $parameters,
            'auth' => [$this->secret_key, '']
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['data'];
    }

    private function getClient(): Client
    {
        return new Client([
            'base_uri' => 'https://api.stripe.com',
            'timeout'  => 10.0,
        ]);
    }
}
