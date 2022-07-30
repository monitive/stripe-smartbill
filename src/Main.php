<?php

namespace App;

use GuzzleHttp\Client;

class Main
{
    public function run()
    {
        echo 'Hello World!';

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'http://httpbin.org',
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);

        $response = $client->request('GET', 'test');
    }
}
