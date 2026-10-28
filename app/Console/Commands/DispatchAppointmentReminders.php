<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind';

    protected $description = 'Queue due staff appointment reminders';

    public function handle(): int
    {
        Appointment::where('status', 'scheduled')->where('reminder_at', '<=', now())->where('starts_at', '>', now())->whereNull('reminder_queued_at')->chunkById(100, function ($records): void {
            foreach ($records as $record) {
                DB::transaction(function () use ($record): void {
                    $row = Appointment::whereKey($record->id)->lockForUpdate()->first();
                    if ($row && ! $row->reminder_queued_at && $row->status === 'scheduled') {
                        $row->update(['reminder_queued_at' => now()]);
                        SendAppointmentReminder::dispatch($row->id, $row->reminder_version);
                    }
                });
            }
        });

        return self::SUCCESS;
    }
}
