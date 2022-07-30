<?php

declare(strict_types=1);

namespace App;

use DateTime;
use GuzzleHttp\Client;

class Stripe
{
    public function getChargeIdsAfterDate(DateTime $date_time): array
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://api.stripe.com',
            // You can set any number of default request options.
            'timeout'  => 10.0,
        ]);
        $response = $client->request('GET', 'v1/charges', [
            'query' => [
                'limit' => 100,
                'created[gte]' => $date_start->getTimestamp(),
            ],
            'auth' => [$this->settings['STRIPE_SECRET_KEY'], '']
        ]);
        $body = json_decode($response->getBody()->getContents(), true);

        $charges = [];
        foreach ($body['data'] as $charge) {
            $charges[] = $charge['id'];
        }
        return $charges;
    }
}
