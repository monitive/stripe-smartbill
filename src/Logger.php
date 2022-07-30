<?php

declare(strict_types=1);

namespace App;

class Logger
{
    public function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
