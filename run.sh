#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use DisplaceTech\ReleaseHashes;

$application = new Application();

// Register commands
$application->add(new ReleaseHashes\WordPressHashesCommand());
$application->add(new ReleaseHashes\PluginHashesCommand());

$application->run();