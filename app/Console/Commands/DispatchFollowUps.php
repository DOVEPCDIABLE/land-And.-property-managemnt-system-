<?php

namespace App\Console\Commands;

use App\Jobs\SendFollowUp;
use App\Models\FollowUp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchFollowUps extends Command
{
    protected $signature = 'followups:dispatch';

    protected $description = 'Queue due follow-ups atomically';

    public function handle(): int
    {
        $count = 0;
        FollowUp::where('status', 'pending')->where('due_at', '<=', now())->chunkById(100, function ($items) use (&$count): void {
            foreach ($items as $item) {
                DB::transaction(function () use ($item, &$count): void {
                    if (FollowUp::whereKey($item->id)->where('status', 'pending')->update(['status' => 'queued'])) {
                        SendFollowUp::dispatch($item->id)->afterCommit();
                        $count++;
                    }
                });
            }
        });
        $this->info("Queued {$count} follow-ups.");

        return self::SUCCESS;
    }
}
