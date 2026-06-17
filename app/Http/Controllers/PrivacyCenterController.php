<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GdprRequest;
use App\Models\User;
use App\Services\Gdpr\GdprAuditService;
use App\Services\Gdpr\GdprExportService;
use App\Services\Gdpr\GdprNotificationService;
use App\Services\Gdpr\GdprRetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyCenterController extends BasePageController
{
    public function index(GdprRetentionService $retentionService): View
    {
        /** @var User $user */
        $user = Auth::user();

        $requests = GdprRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        $this->viewData = array_merge($this->viewData, [
            'user' => $user,
            'gdprRequests' => $requests,
            'retentionPolicy' => $retentionService->policy(),
            'meta_title' => 'Privacy Center',
            'meta_keywords' => 'privacy,gdpr,data export,erasure',
            'meta_description' => 'Manage your privacy and GDPR data requests',
        ]);

        return view('privacy-center.index', $this->viewData);
    }

    public function requestExport(Request $request, GdprExportService $exportService, GdprAuditService $auditService): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'requester_username' => $user->username,
            'requester_email' => $user->email,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_PROCESSING,
            'request_payload' => [
                'requested_by' => 'self_service',
                'ip_recorded' => (bool) config('nntmux_settings.store_user_ip'),
            ],
            'notes' => $validated['notes'] ?? null,
        ]);

        $auditService->record(
            event: 'request_submitted',
            description: 'Self-service GDPR export request submitted.',
            subject: $user,
            actor: $user,
            request: $gdprRequest,
            metadata: ['type' => GdprRequest::TYPE_EXPORT]
        );

        $exportService->generate($user, $gdprRequest, $user);

        return redirect()
            ->route('privacy-center.index')
            ->with('success', 'Your data export has been generated and is available for 7 days.');
    }

    public function requestErasure(Request $request, GdprAuditService $auditService, GdprNotificationService $notificationService): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasRole('Admin')) {
            return redirect()
                ->route('privacy-center.index')
                ->with('error', 'Admin accounts cannot submit self-service erasure requests.');
        }

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'in:ERASE'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $openRequestExists = GdprRequest::query()
            ->where('user_id', $user->id)
            ->where('type', GdprRequest::TYPE_ERASURE)
            ->open()
            ->exists();

        if ($openRequestExists) {
            return redirect()
                ->route('privacy-center.index')
                ->with('error', 'You already have an open account erasure request.');
        }

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'requester_username' => $user->username,
            'requester_email' => $user->email,
            'type' => GdprRequest::TYPE_ERASURE,
            'status' => GdprRequest::STATUS_PENDING,
            'request_payload' => [
                'requested_by' => 'self_service',
                'confirmation' => $validated['confirmation'],
            ],
            'notes' => $validated['notes'] ?? null,
        ]);

        $auditService->record(
            event: 'request_submitted',
            description: 'Self-service GDPR erasure request submitted.',
            subject: $user,
            actor: $user,
            request: $gdprRequest,
            metadata: ['type' => GdprRequest::TYPE_ERASURE]
        );

        $notificationService->requestSubmitted($gdprRequest);

        return redirect()
            ->route('privacy-center.index')
            ->with('success', 'Your account erasure request has been submitted for administrator review.');
    }

    public function downloadExport(GdprRequest $gdprRequest): StreamedResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ((int) $gdprRequest->user_id !== (int) $user->id || ! $gdprRequest->isDownloadableExport()) {
            return redirect()->route('privacy-center.index')->with('error', 'The requested export is not available.');
        }

        $disk = $gdprRequest->export_disk ?: 'local';
        if (! Storage::disk($disk)->exists($gdprRequest->export_path)) {
            return redirect()->route('privacy-center.index')->with('error', 'The requested export file has expired or is missing.');
        }

        return Storage::disk($disk)->download($gdprRequest->export_path, 'gdpr-export-'.$user->id.'.json');
    }
}
