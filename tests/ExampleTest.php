<?php declare(strict_types=1);

namespace App;

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testItReturnsNumber()
    {
        $main = new Main();
        $this->assertSame(69, $main->generate());
    }
}
