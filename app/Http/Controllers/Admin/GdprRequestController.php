<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\GdprRequest;
use App\Models\User;
use App\Services\Gdpr\GdprAuditService;
use App\Services\Gdpr\GdprErasureService;
use App\Services\Gdpr\GdprExportService;
use App\Services\Gdpr\GdprNotificationService;
use App\Services\Gdpr\GdprRetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GdprRequestController extends BasePageController
{
    /**
     * @var list<string>
     */
    private const TYPES = [
        GdprRequest::TYPE_EXPORT,
        GdprRequest::TYPE_ERASURE,
        GdprRequest::TYPE_RECTIFICATION,
        GdprRequest::TYPE_RESTRICTION,
    ];

    /**
     * @var list<string>
     */
    private const STATUSES = [
        GdprRequest::STATUS_PENDING,
        GdprRequest::STATUS_PROCESSING,
        GdprRequest::STATUS_COMPLETED,
        GdprRequest::STATUS_REJECTED,
        GdprRequest::STATUS_CANCELLED,
    ];

    public function index(Request $request, GdprRetentionService $retentionService): View
    {
        $this->setAdminPrefs();

        $type = $request->string('type')->trim()->value();
        $status = $request->string('status')->trim()->value();

        if (! in_array($type, self::TYPES, true)) {
            $type = '';
        }

        if (! in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $requests = GdprRequest::query()
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'processing' THEN 1 WHEN 'completed' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->paginate((int) config('nntmux.items_per_page', 25))
            ->withQueryString();

        $this->viewData = array_merge($this->viewData, [
            'requests' => $requests,
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'type' => $type,
            'status' => $status,
            'retentionPolicy' => $retentionService->policy(),
            'title' => 'GDPR Requests',
            'meta_title' => 'GDPR Requests',
            'meta_keywords' => 'gdpr,privacy,requests,admin',
            'meta_description' => 'Manage GDPR data subject requests',
        ]);

        return view('admin.gdpr.index', $this->viewData);
    }

    public function show(GdprRequest $gdprRequest): View
    {
        $this->setAdminPrefs();

        $gdprRequest->load(['auditLogs', 'processor']);
        $subject = $this->subjectFor($gdprRequest);

        $this->viewData = array_merge($this->viewData, [
            'gdprRequest' => $gdprRequest,
            'subject' => $subject,
            'title' => 'GDPR Request #'.$gdprRequest->id,
            'meta_title' => 'GDPR Request #'.$gdprRequest->id,
            'meta_keywords' => 'gdpr,privacy,request,admin',
            'meta_description' => 'Review GDPR data subject request',
        ]);

        return view('admin.gdpr.show', $this->viewData);
    }

    public function generateExport(GdprRequest $gdprRequest, GdprExportService $exportService): RedirectResponse
    {
        if ($gdprRequest->type !== GdprRequest::TYPE_EXPORT || ! $this->isOpen($gdprRequest)) {
            return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('error', 'This request cannot be exported.');
        }

        $subject = $this->subjectFor($gdprRequest);
        if (! $subject instanceof User) {
            return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('error', 'The subject user could not be found.');
        }

        $actor = $this->actor();

        $gdprRequest->update([
            'status' => GdprRequest::STATUS_PROCESSING,
            'processed_by' => $actor?->id,
        ]);

        $exportService->generate($subject, $gdprRequest, $actor);

        return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('success', 'GDPR export generated successfully.');
    }

    public function completeErasure(GdprRequest $gdprRequest, GdprErasureService $erasureService): RedirectResponse
    {
        if ($gdprRequest->type !== GdprRequest::TYPE_ERASURE || ! $this->isOpen($gdprRequest)) {
            return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('error', 'This erasure request cannot be completed.');
        }

        $subject = $this->subjectFor($gdprRequest);
        if (! $subject instanceof User) {
            return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('error', 'The subject user could not be found.');
        }

        if ($subject->hasRole('Admin')) {
            return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('error', 'Admin accounts cannot be erased from the GDPR request queue.');
        }

        $actor = $this->actor();

        $gdprRequest->update([
            'status' => GdprRequest::STATUS_PROCESSING,
            'processed_by' => $actor?->id,
        ]);

        $erasureService->eraseForAccountDeletion($subject, $actor, $gdprRequest);

        return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('success', 'Account erasure completed. Retained payment and audit records were anonymized or minimized where practical.');
    }

    public function reject(Request $request, GdprRequest $gdprRequest, GdprAuditService $auditService, GdprNotificationService $notificationService): RedirectResponse
    {
        if (! $this->isOpen($gdprRequest)) {
            return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('error', 'This request is already closed.');
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:4000'],
        ]);

        $actor = $this->actor();
        $subject = $this->subjectFor($gdprRequest);

        $gdprRequest->update([
            'status' => GdprRequest::STATUS_REJECTED,
            'admin_notes' => $validated['admin_notes'],
            'processed_by' => $actor?->id,
            'completed_at' => now(),
            'response_payload' => [
                'rejected' => true,
                'reason' => $validated['admin_notes'],
            ],
        ]);

        $auditService->record(
            event: 'request_rejected',
            description: 'GDPR request rejected by an administrator.',
            subject: $subject,
            actor: $actor,
            request: $gdprRequest,
            metadata: ['type' => $gdprRequest->type]
        );

        $notificationService->requestRejected($gdprRequest->fresh() ?? $gdprRequest);

        return redirect()->route('admin.gdpr-requests.show', $gdprRequest)->with('success', 'GDPR request rejected.');
    }

    private function subjectFor(GdprRequest $gdprRequest): ?User
    {
        if ($gdprRequest->user_id === null) {
            return null;
        }

        return User::withTrashed()->find($gdprRequest->user_id);
    }

    private function actor(): ?User
    {
        $actor = Auth::user();

        return $actor instanceof User ? $actor : null;
    }

    private function isOpen(GdprRequest $gdprRequest): bool
    {
        return in_array($gdprRequest->status, [GdprRequest::STATUS_PENDING, GdprRequest::STATUS_PROCESSING], true);
    }
}
