<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminBlockedIpStoreRequest;
use App\Models\BlockedIp;
use App\Services\Security\IpBlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminBlockedIpController extends BasePageController
{
    public function __construct(private readonly IpBlockService $blocks)
    {
        parent::__construct();
    }

    public function index(): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Blocked IPs';

        $this->viewData = array_merge($this->viewData, [
            'blocks' => BlockedIp::query()
                ->orderByDesc('blocked_at')
                ->orderByDesc('id')
                ->paginate((int) config('nntmux.items_per_page', 50)),
            'activeCount' => BlockedIp::query()->active()->count(),
            'enforcementEnabled' => (bool) config('nntmux.ip_blocking.enabled'),
            'autoBlockEnabled' => (bool) config('nntmux.ip_blocking.auto_block') && (bool) config('suspicious-login.enabled'),
            'threshold' => (int) config('nntmux.ip_blocking.unknown_account_threshold'),
            'windowHours' => (int) config('nntmux.ip_blocking.window_hours'),
            'blockHours' => (int) config('nntmux.ip_blocking.block_hours'),
            'title' => $title,
            'meta_title' => $meta_title,
            'page_title' => $title,
        ]);

        return view('admin.blocked-ips.index', $this->viewData);
    }

    public function store(AdminBlockedIpStoreRequest $request): RedirectResponse
    {
        $hours = $request->hours();
        $reason = trim((string) $request->validated('reason'));

        $block = $this->blocks->blockManually(
            (string) $request->validated('ip'),
            $hours === null ? null : now()->addHours($hours),
            $reason === '' ? null : $reason,
            (int) $request->user()?->id,
        );

        if ($block === null) {
            return redirect()->back(fallback: route('admin.blocked-ips.index'))
                ->withErrors(['ip' => 'That address cannot be blocked.']);
        }

        return redirect()->back(fallback: route('admin.blocked-ips.index'))
            ->with('success', $hours === null
                ? "{$block->ip_address} is blocked until the block is lifted."
                : "{$block->ip_address} is blocked for {$hours} ".($hours === 1 ? 'hour' : 'hours').'.');
    }

    public function destroy(BlockedIp $blockedIp): RedirectResponse
    {
        $this->blocks->lift($blockedIp->ip_address);

        return redirect()
            ->route('admin.blocked-ips.index')
            ->with('success', "The block on {$blockedIp->ip_address} was lifted.");
    }
}
