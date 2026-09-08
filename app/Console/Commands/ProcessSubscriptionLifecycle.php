<?php

namespace App\Console\Commands;

use App\Actions\Organizations\ProcessSubscriptionLifecycleAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:process-lifecycle')]
#[Description('Send trial-ending reminders and transition subscriptions through trial -> suspended / active -> past_due -> suspended. Meant to run daily via the scheduler.')]
class ProcessSubscriptionLifecycle extends Command
{
    public function handle(ProcessSubscriptionLifecycleAction $action): int
    {
        $stats = $action->execute();

        $this->info(sprintf(
            'Reminder trial: %d · Trial berakhir → suspended: %d · Active → past_due: %d · Past_due → suspended: %d',
            $stats['trial_reminders'],
            $stats['trial_suspended'],
            $stats['past_due'],
            $stats['suspended'],
        ));

        return self::SUCCESS;
    }
}
