<?php

declare(strict_types=1);

namespace App\Tests\Common\Process;

use App\Common\Process\ProcessFixture;
use PHPUnit\Framework\TestCase;

class ProcessFixtureTest extends TestCase
{
    public function test()
    {
        $fixture = new ProcessFixture('output', '', true);

        self::assertEquals('output', $fixture->getOutput());
        self::assertEquals('', $fixture->getErrorOutput());
        self::assertTrue($fixture->isSuccessful());
    }
}