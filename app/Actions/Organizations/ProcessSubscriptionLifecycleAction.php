<?php

namespace App\Actions\Organizations;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\Organizations\PlanDowngradeAppliedNotification;
use App\Notifications\Organizations\PlanDowngradeSkippedNotification;
use App\Notifications\Organizations\SubscriptionCanceledNotification;
use App\Notifications\Organizations\SubscriptionPastDueNotification;
use App\Notifications\Organizations\SubscriptionSuspendedNotification;
use App\Notifications\Organizations\TrialEndingSoonNotification;

class ProcessSubscriptionLifecycleAction
{
    /**
     * How many days before trial_ends_at to send the "trial akan
     * berakhir" reminder.
     */
    private const TRIAL_REMINDER_DAYS_BEFORE = 3;

    /**
     * How many days an Active subscription gets to stay usable
     * (PastDue) after current_period_end passes, before being Suspended.
     */
    private const GRACE_PERIOD_DAYS = 3;

    /**
     * Walk every subscription that could need a state transition or a
     * reminder email today. Meant to run once a day via the scheduler
     * (see routes/console.php).
     *
     * @return array{trial_reminders: int, trial_suspended: int, past_due: int, suspended: int, canceled: int, downgraded: int, downgrade_skipped: int}
     */
    public function execute(): array
    {
        $stats = ['trial_reminders' => 0, 'trial_suspended' => 0, 'past_due' => 0, 'suspended' => 0, 'canceled' => 0, 'downgraded' => 0, 'downgrade_skipped' => 0];

        $this->sendTrialReminders($stats);
        $this->expireTrials($stats);
        $this->finalizeVoluntaryCancellations($stats);
        $this->finalizePendingDowngrades($stats);
        $this->markPastDue($stats);
        $this->suspendOverdue($stats);

        return $stats;
    }

    private function sendTrialReminders(array &$stats): void
    {
        Subscription::query()
            ->where('status', SubscriptionStatus::Trial)
            ->whereNull('trial_reminder_sent_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now()->addDays(self::TRIAL_REMINDER_DAYS_BEFORE))
            ->where('trial_ends_at', '>', now())
            ->with('organization')
            ->each(function (Subscription $subscription) use (&$stats) {
                $subscription->organization->owners()->each(
                    fn ($owner) => $owner->notify(new TrialEndingSoonNotification($subscription))
                );

                $subscription->update(['trial_reminder_sent_at' => now()]);
                $stats['trial_reminders']++;
            });
    }

    private function expireTrials(array &$stats): void
    {
        Subscription::query()
            ->where('status', SubscriptionStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->with('organization')
            ->each(function (Subscription $subscription) use (&$stats) {
                $subscription->update(['status' => SubscriptionStatus::Suspended]);

                $subscription->organization->owners()->each(
                    fn ($owner) => $owner->notify(new SubscriptionSuspendedNotification($subscription))
                );

                $stats['trial_suspended']++;
            });
    }

    /**
     * Subscriptions the user voluntarily canceled (`canceled_at` set via
     * CancelSubscriptionAction) that have now reached the end of their
     * paid period. These go straight to `canceled` — NOT `past_due` —
     * because this isn't a billing failure, it's an intentional
     * cancellation. Handled BEFORE markPastDue() so it never
     * double-processes these as overdue payments.
     */
    private function finalizeVoluntaryCancellations(array &$stats): void
    {
        Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->whereNotNull('canceled_at')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->with('organization')
            ->each(function (Subscription $subscription) use (&$stats) {
                $subscription->update(['status' => SubscriptionStatus::Canceled]);

                $subscription->organization->owners()->each(
                    fn ($owner) => $owner->notify(new SubscriptionCanceledNotification($subscription))
                );

                $stats['canceled']++;
            });
    }

    /**
     * Apply any downgrade scheduled via RequestPlanDowngradeAction whose
     * current_period_end has now passed. Re-checks the store quota at
     * this point (not just when the downgrade was requested) — the
     * store count could have grown in the meantime. If it no longer
     * fits, the downgrade is dropped (not retried) and the owner is
     * notified, rather than silently leaving them stuck.
     */
    private function finalizePendingDowngrades(array &$stats): void
    {
        Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->whereNotNull('pending_plan_id')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->with(['organization', 'pendingPlan'])
            ->each(function (Subscription $subscription) use (&$stats) {
                $targetPlan = $subscription->pendingPlan;
                $currentStoreCount = $subscription->organization->teams()->count();

                if ($targetPlan->max_stores !== null && $currentStoreCount > $targetPlan->max_stores) {
                    $subscription->update(['pending_plan_id' => null]);

                    $subscription->organization->owners()->each(
                        fn ($owner) => $owner->notify(new PlanDowngradeSkippedNotification($subscription, $targetPlan))
                    );

                    $stats['downgrade_skipped']++;

                    return;
                }

                $subscription->update([
                    'plan_id' => $targetPlan->id,
                    'pending_plan_id' => null,
                    'max_stores_override' => null,
                    'max_owners_override' => null,
                ]);

                $subscription->organization->owners()->each(
                    fn ($owner) => $owner->notify(new PlanDowngradeAppliedNotification($subscription->fresh()))
                );

                $stats['downgraded']++;
            });
    }

    private function markPastDue(array &$stats): void
    {
        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNull('canceled_at') // voluntary cancellation handled separately above
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->with('organization')
            ->each(function (Subscription $subscription) use (&$stats) {
                $subscription->update(['status' => SubscriptionStatus::PastDue]);

                $subscription->organization->owners()->each(
                    fn ($owner) => $owner->notify(new SubscriptionPastDueNotification($subscription, self::GRACE_PERIOD_DAYS))
                );

                $stats['past_due']++;
            });
    }

    private function suspendOverdue(array &$stats): void
    {
        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->whereNull('canceled_at') // voluntary cancellation handled separately above
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now()->subDays(self::GRACE_PERIOD_DAYS))
            ->with('organization')
            ->each(function (Subscription $subscription) use (&$stats) {
                $subscription->update(['status' => SubscriptionStatus::Suspended]);

                $subscription->organization->owners()->each(
                    fn ($owner) => $owner->notify(new SubscriptionSuspendedNotification($subscription))
                );

                $stats['suspended']++;
            });
    }
}
