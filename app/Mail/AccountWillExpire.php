<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\HasBrandedSubject;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountWillExpire extends Mailable
{
    use HasBrandedSubject, Queueable, SerializesModels;

    public int $days;

    public string $username;

    public string $account;

    public string $site;

    public ?string $preheader;

    public bool $hasPendingRole;

    public ?string $pendingRoleName;

    public ?string $pendingRoleStartDate;

    private string $siteEmail;

    public function __construct(User $user, int $days)
    {
        $roleExpiryInfo = $user->getRoleExpiryInfo();
        $pendingRole = $roleExpiryInfo['pending_role'];
        $pendingStart = $roleExpiryInfo['pending_start'];

        $this->days = $days;
        $this->username = (string) $user->username;
        $this->account = (string) ($user->role->name ?? 'User');
        $this->hasPendingRole = (bool) $roleExpiryInfo['has_pending_role'] && $pendingRole !== null && $pendingStart !== null;
        $this->pendingRoleName = $this->hasPendingRole ? (string) $pendingRole->name : null;
        $this->pendingRoleStartDate = $this->hasPendingRole ? $pendingStart->toFormattedDateString() : null;
        $this->siteEmail = (string) config('mail.from.address');
        $this->site = (string) config('app.name');
        $this->preheader = $this->hasPendingRole
            ? "Your {$this->account} role expires in {$this->days} day(s), then {$this->pendingRoleName} is scheduled."
            : "Your {$this->account} role expires in {$this->days} day(s).";
    }

    public function build(): static
    {
        return $this->from($this->siteEmail)
            ->brandedSubject('Your account is about to expire')
            ->markdown('emails.markdown.accountWillExpire', [
                'username' => $this->username,
                'account' => $this->account,
                'days' => $this->days,
                'hasPendingRole' => $this->hasPendingRole,
                'pendingRoleName' => $this->pendingRoleName,
                'pendingRoleStartDate' => $this->pendingRoleStartDate,
                'site' => $this->site,
                'preheader' => $this->preheader,
            ]);
    }
}
