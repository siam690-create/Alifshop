<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pathao:login', function () {
    $script = base_path('scripts/pathao-phone-summary.mjs');

    if (!file_exists($script)) {
        $this->error('Pathao automation script not found.');
        return 1;
    }

    $process = new Process(['node', $script, 'login'], base_path());
    $process->setTimeout(null);
    $process->setTty(false);
    $process->run(function ($type, $output) {
        $this->output->write($output);
    });

    return $process->isSuccessful() ? 0 : 1;
})->purpose('Open Pathao login for automation and save the merchant session');
