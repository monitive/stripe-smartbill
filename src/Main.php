<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use GuzzleHttp\Client;

class Main
{
    public static function remoteStart(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->required('DATABASE_DSN');
        $dotenv->load();

        $main = new self();
        $main->run();
    }

    public function run()
    {
        echo 'Hello World!';

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $_ENV['TARGET_URL'],
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);

        $response = $client->request('GET', 'test');
    }

    public function check()
    {
        return 69;
    }
}
