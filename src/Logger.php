<?php

declare(strict_types=1);

namespace App;

/**
 * Very basic Logger class that just outputs to the console.
 */
class Logger
{
    public function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
