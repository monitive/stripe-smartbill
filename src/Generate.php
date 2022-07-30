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

        foreach ($body['data'] as $charge) {
            echo $charge['id'] . ' ' . $charge['amount'] . ' ' . $charge['created'] . ' ';
            echo DateTime::createFromFormat('U', (string)$charge['created'])->format('Y-m-d') . "\n";
        }
    }
}
