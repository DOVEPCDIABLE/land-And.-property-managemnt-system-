<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('followups:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->hourly()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
