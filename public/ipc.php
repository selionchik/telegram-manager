<?php
// public/ipc.php
require __DIR__.'/../vendor/autoload.php';

use danog\MadelineProto\Ipc\Runner\WebRunner;

WebRunner::run();