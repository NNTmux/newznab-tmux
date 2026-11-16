<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

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
     * When the invoice_status column was added (its migration's timestamp). A row created
     * before this that is still Pending predates settlement tracking on any install.
     */
    public const string LEGACY_INVOICE_STATUS_BEFORE = '2024-11-16 11:46:40';

    /** The role upgrade was applied. */
    public const string STATE_SETTLED = 'settled';

    /** Paid, but the role upgrade has not been applied. */
    public const string STATE_UNSETTLED = 'unsettled';

    /** Pending only because the row predates settlement tracking. */
    public const string STATE_LEGACY = 'legacy';

    /**
     * @var list<string>
     */
    public const array INVOICE_STATES = [self::STATE_SETTLED, self::STATE_UNSETTLED, self::STATE_LEGACY];

    /**
     * Coin of a BTCPay payment method id; "BTC" and "BTC-CHAIN" are both BTC, "LBTC-CHAIN" is LBTC.
     */
    public static function coinFor(?string $method): string
    {
        $coin = strtoupper(trim(explode('-', (string) $method, 2)[0]));

        return $coin === '' ? 'Unknown' : $coin;
    }

    /**
     * Raw payment_method values stored for a coin, for filtering by coin.
     *
     * @return list<string>
     */
    public static function methodsForCoin(string $coin): array
    {
        $coin = strtoupper(trim($coin));

        return static::query()
            ->distinct()
            ->pluck('payment_method')
            ->filter(static fn (mixed $method): bool => strtoupper(self::coinFor((string) $method)) === $coin)
            ->map(static fn (mixed $method): string => (string) $method)
            ->values()
            ->all();
    }

    /**
     * Invoice state for a stored invoice_status and creation time.
     */
    public static function invoiceStateFor(?string $invoiceStatus, ?CarbonInterface $createdAt): string
    {
        if (strcasecmp((string) $invoiceStatus, self::INVOICE_STATUS_SETTLED) === 0) {
            return self::STATE_SETTLED;
        }

        return $createdAt !== null && $createdAt->lt(Carbon::parse(self::LEGACY_INVOICE_STATUS_BEFORE))
            ? self::STATE_LEGACY
            : self::STATE_UNSETTLED;
    }

    public function invoiceState(): string
    {
        return self::invoiceStateFor($this->invoice_status, $this->created_at);
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeInvoiceState(Builder $query, string $state): Builder
    {
        if ($state === self::STATE_SETTLED) {
            return $query->where('payments.invoice_status', self::INVOICE_STATUS_SETTLED);
        }

        if (! \in_array($state, [self::STATE_UNSETTLED, self::STATE_LEGACY], true)) {
            return $query;
        }

        $query->where(function (Builder $notSettled): void {
            $notSettled->whereNull('payments.invoice_status')
                ->orWhere('payments.invoice_status', '<>', self::INVOICE_STATUS_SETTLED);
        });

        if ($state === self::STATE_LEGACY) {
            return $query->where('payments.created_at', '<', self::LEGACY_INVOICE_STATUS_BEFORE);
        }

        return $query->where(function (Builder $tracked): void {
            $tracked->whereNull('payments.created_at')
                ->orWhere('payments.created_at', '>=', self::LEGACY_INVOICE_STATUS_BEFORE);
        });
    }

    /**
     * Filters of the admin payments list; empty values are ignored.
     *
     * @param  Builder<Payment>  $query
     * @param  array<string, string>  $filters  Keys: q, username, user, email, plan, coin, invoice_status,
     *                                          payment_status, amount_min, amount_max, start_date, end_date, payer, account.
     * @return Builder<Payment>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $value = static fn (string $key): string => trim((string) ($filters[$key] ?? ''));

        if ($value('q') !== '') {
            $reference = '%'.$value('q').'%';
            $query->where(function (Builder $references) use ($reference): void {
                $references->where('payments.order_id', 'like', $reference)
                    ->orWhere('payments.invoice_id', 'like', $reference)
                    ->orWhere('payments.payment_id', 'like', $reference);
            });
        }

        if ($value('username') !== '') {
            $query->where('payments.username', 'like', '%'.$value('username').'%');
        }

        if ($value('user') !== '') {
            $query->where('payments.username', $value('user'));
        }

        if ($value('email') !== '') {
            $query->where('payments.email', 'like', '%'.$value('email').'%');
        }

        if ($value('plan') !== '') {
            $query->where('payments.item_description', $value('plan'));
        }

        if ($value('coin') !== '') {
            $query->whereIn('payments.payment_method', self::methodsForCoin($value('coin')));
        }

        if ($value('invoice_status') !== '') {
            $query->invoiceState(strtolower($value('invoice_status')));
        }

        if ($value('payment_status') !== '') {
            $query->where('payments.payment_status', $value('payment_status'));
        }

        if (is_numeric($value('amount_min'))) {
            $query->whereRaw('CAST(payments.invoice_amount AS DECIMAL(20,8)) >= ?', [(float) $value('amount_min')]);
        }

        if (is_numeric($value('amount_max'))) {
            $query->whereRaw('CAST(payments.invoice_amount AS DECIMAL(20,8)) <= ?', [(float) $value('amount_max')]);
        }

        if ($value('start_date') !== '') {
            try {
                $query->where('payments.created_at', '>=', Carbon::parse($value('start_date'))->startOfDay());
            } catch (\Exception) {
                // ignore invalid date input
            }
        }

        if ($value('end_date') !== '') {
            try {
                $query->where('payments.created_at', '<=', Carbon::parse($value('end_date'))->endOfDay());
            } catch (\Exception) {
                // ignore invalid date input
            }
        }

        if (\in_array($value('payer'), ['returning', 'first_time'], true)) {
            $earlierPayment = static function (QueryBuilder $earlier): void {
                $earlier->from('payments as earlier_payments')
                    ->whereColumn('earlier_payments.username', 'payments.username')
                    ->whereColumn('earlier_payments.id', '<', 'payments.id');
            };

            $value('payer') === 'returning' ? $query->whereExists($earlierPayment) : $query->whereNotExists($earlierPayment);
        }

        if (\in_array($value('account'), ['linked', 'missing'], true)) {
            $account = static function (QueryBuilder $users): void {
                $users->from('users')
                    ->whereColumn('users.username', 'payments.username')
                    ->whereNull('users.deleted_at');
            };

            $value('account') === 'linked' ? $query->whereExists($account) : $query->whereNotExists($account);
        }

        return $query;
    }
}
