<?php

declare(strict_types=1);
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

// Pest bootstrap. Laravel tests extend Orchestra\Testbench\TestCase as classes,
// so no global test case is bound here.

/** What Symfony VarDumper (so dd(), dump(), Telescope and Ignition) prints for `$value`. */
function cliDump(mixed $value): string
{
    return (string) (new CliDumper)
        ->dump((new VarCloner)->cloneVar($value), true);
}
