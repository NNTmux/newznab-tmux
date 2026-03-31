<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $guarded = [];

    /** Stored from BTCPay webhook payload.payment.status; duplicate checks use Settled. */
    public const PAYMENT_STATUS_SETTLED = 'Settled';

    /** Migration default; webhook clears to Settled after role upgrade. */
    public const INVOICE_STATUS_PENDING = 'Pending';

    public const INVOICE_STATUS_SETTLED = 'Settled';

    /**
     * @param  Builder<Payment>  $query
     * @param  array{username?: string, email?: string, payment_status?: string, invoice_status?: string}  $filters
     * @return Builder<Payment>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['username'])) {
            $query->where('username', 'like', '%'.$filters['username'].'%');
        }

        if (! empty($filters['email'])) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['invoice_status'])) {
            if ($filters['invoice_status'] === self::INVOICE_STATUS_PENDING) {
                $query->where(function (Builder $q): void {
                    $q->whereNull('invoice_status')
                        ->orWhere('invoice_status', self::INVOICE_STATUS_PENDING);
                });
            } else {
                $query->where('invoice_status', $filters['invoice_status']);
            }
        }

        return $query;
    }
}
