<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\RegistrationPeriodRequest;
use App\Http\Requests\Admin\UpdateRegistrationStatusRequest;
use App\Models\RegistrationPeriod;
use App\Models\RegistrationStatusHistory;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\RegistrationFailureLogService;
use App\Services\RegistrationStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminRegistrationController extends BasePageController
{
    public function __construct(
        private readonly RegistrationStatusService $registrationStatusService,
        private readonly RegistrationFailureLogService $registrationFailureLogService
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $editingPeriod = null;
        if ($request->filled('edit_period')) {
            $editingPeriod = RegistrationPeriod::query()
                ->with(['createdByUser', 'updatedByUser'])
                ->find((int) $request->integer('edit_period'));

            if ($editingPeriod === null) {
                return redirect()
                    ->route('admin.registrations.index')
                    ->with('error', 'The selected registration period could not be found.');
            }
        }

        $status = $this->registrationStatusService->resolve();
        $periods = RegistrationPeriod::query()
            ->with(['createdByUser', 'updatedByUser'])
            ->orderByDesc('starts_at')
            ->get();
        $history = RegistrationStatusHistory::query()
            ->with(['changedByUser', 'registrationPeriod'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $recentSuccessfulRegistrations = UserActivity::query()
            ->where('activity_type', 'registered')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->filter(function (UserActivity $activity): bool {
                return is_array($activity->metadata)
                    && isset($activity->metadata['email'], $activity->metadata['ip_address']);
            })
            ->take(10)
            ->values();

        $recentFailedAttempts = collect($this->registrationFailureLogService->recentFailures(10))
            ->map(function (array $entry): array {
                $entry['registration_status_label'] = is_numeric($entry['registration_status'])
                    ? $this->registrationStatusService->statusLabel((int) $entry['registration_status'])
                    : null;
                $entry['manual_registration_status_label'] = is_numeric($entry['manual_registration_status'])
                    ? $this->registrationStatusService->statusLabel((int) $entry['manual_registration_status'])
                    : null;

                return $entry;
            })
            ->all();

        return view('admin.registrations.index', array_merge($this->viewData, [
            'title' => 'Registration Admin',
            'page_title' => 'Registration Admin',
            'meta_title' => 'Registration Admin',
            'meta_description' => 'Manage registration status, scheduled open periods, and registration activity.',
            'registrationStatus' => $status,
            'statusOptions' => $this->registrationStatusService->statusOptions(),
            'periods' => $periods,
            'editingPeriod' => $editingPeriod,
            'history' => $history,
            'recentSuccessfulRegistrations' => $recentSuccessfulRegistrations,
            'recentFailedAttempts' => $recentFailedAttempts,
        ]));
    }

    public function updateStatus(UpdateRegistrationStatusRequest $request): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        $newStatus = (int) $request->integer('registerstatus');
        $oldStatus = $this->registrationStatusService->getManualStatus();

        $this->registrationStatusService->updateManualStatus(
            $newStatus,
            $admin,
            $request->input('note')
        );

        $message = $oldStatus === $newStatus
            ? 'Registration status note saved without changing the manual mode.'
            : 'Manual registration status updated successfully.';

        return redirect()
            ->route('admin.registrations.index')
            ->with('success', $message);
    }

    public function storePeriod(RegistrationPeriodRequest $request): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        $this->registrationStatusService->createPeriod(
            array_merge($request->validated(), [
                'is_enabled' => $request->boolean('is_enabled'),
            ]),
            $admin
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with('success', 'Scheduled open-registration period created successfully.');
    }

    public function updatePeriod(RegistrationPeriodRequest $request, RegistrationPeriod $period): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        $this->registrationStatusService->updatePeriod(
            $period,
            array_merge($request->validated(), [
                'is_enabled' => $request->boolean('is_enabled'),
            ]),
            $admin
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with('success', 'Scheduled open-registration period updated successfully.');
    }

    public function togglePeriod(Request $request, RegistrationPeriod $period): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        $this->registrationStatusService->togglePeriod(
            $period,
            $admin,
            $request->input('note')
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with('success', 'Scheduled open-registration period status updated successfully.');
    }

    public function destroyPeriod(Request $request, RegistrationPeriod $period): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        $this->registrationStatusService->deletePeriod(
            $period,
            $admin,
            $request->input('note')
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with('success', 'Scheduled open-registration period deleted successfully.');
    }
}
