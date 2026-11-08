<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('listings:prune')->dailyAt('03:15');
Schedule::command('queue:prune-failed --hours=720')->daily();
