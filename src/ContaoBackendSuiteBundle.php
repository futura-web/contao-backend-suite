<?php

declare(strict_types=1);

namespace Futura\ContaoBackendSuite;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ContaoBackendSuiteBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
