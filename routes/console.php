<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas (Laravel Scheduler)
|--------------------------------------------------------------------------
| En producción basta con un cron que ejecute cada minuto:
|   * * * * * php /ruta/al/proyecto/artisan schedule:run
| En local puedes probarlo con:  php artisan schedule:work
| o lanzar la tarea directamente: php artisan facturas:recordatorios
*/

Schedule::command('facturas:recordatorios')
    ->dailyAt('09:00')
    ->withoutOverlapping();
