<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Sync automático API Python → MySQL → análises
|--------------------------------------------------------------------------
| Requer containers `scheduler` + `queue` (docker-compose).
| Sync puxa só gestantes novas; reprocess enfileira jobs faltantes.
*/

Schedule::command('dataset:sync-from-api --only-new')
    ->everyFifteenMinutes()
    ->withoutOverlapping(40)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/dataset-sync.log'));

Schedule::command('analises:reprocess --only-missing')
    ->cron('5,20,35,50 * * * *')
    ->withoutOverlapping(40)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/analises-reprocess.log'));

/*
|--------------------------------------------------------------------------
| Lembretes WhatsApp (agendado_para = hoje)
|--------------------------------------------------------------------------
| Requer WHATSAPP_AUTO_LEMBRETE=true, WAHA WORKING e containers scheduler + queue.
*/
Schedule::command('whatsapp:lembretes-agendados')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone', 'America/Sao_Paulo'))
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/whatsapp-lembretes.log'));
