<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RolePromotion;
use Illuminate\Support\Carbon;

class RolePromotionObserver
{
    /**
     * Handle the RolePromotion "saving" event.
     * Automatically disable promotion if end date has passed
     */
    public function saving(RolePromotion $promotion): void
    {
        // If the promotion has an end date that has passed, automatically disable it
        if ($promotion->end_date && Carbon::now()->gt($promotion->end_date)) {
            $promotion->is_active = false;
        }
    }

    /**
     * Handle the RolePromotion "retrieved" event.
     * This helps ensure that when a promotion is loaded from the database,
     * we check if it should be disabled
     */
    public function retrieved(RolePromotion $promotion): void
    {
        // If the promotion has an end date that has passed but is still marked as active,
        // we update it (this will trigger saving event)
        if ($promotion->is_active && $promotion->end_date && Carbon::now()->gt($promotion->end_date)) {
            $promotion->is_active = false;
            $promotion->saveQuietly(); // Save without triggering events to avoid recursion
        }
    }
}
