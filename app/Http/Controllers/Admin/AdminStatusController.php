<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentStatusEnum;
use App\Enums\ServiceStatusEnum;
use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\StoreIncidentRequest;
use App\Http\Requests\Admin\UpdateIncidentRequest;
use App\Http\Requests\Admin\UpdateServiceHealthRequest;
use App\Models\ServiceIncident;
use App\Models\ServiceStatus;
use App\Services\SiteStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminStatusController extends BasePageController
{
    public function __construct(
        protected SiteStatusService $siteStatusService
    ) {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function index(): View
    {
        $this->setAdminPrefs();

        $services = $this->siteStatusService->getAllServicesForAdmin();
        $incidents = ServiceIncident::query()
            ->with(['services', 'creator'])
            ->orderByDesc('started_at')
            ->paginate(20)
            ->withQueryString();

        $this->viewData = array_merge($this->viewData, [
            'services' => $services,
            'incidents' => $incidents,
            'meta_title' => 'Site status',
            'title' => 'Site status',
        ]);

        return view('admin.status.index', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function create(): View
    {
        $this->setAdminPrefs();

        $services = $this->siteStatusService->getAllServicesForAdmin();

        $this->viewData = array_merge($this->viewData, [
            'services' => $services,
            'meta_title' => 'Create incident',
            'title' => 'Create incident',
        ]);

        return view('admin.status.create', $this->viewData);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();

        if ($data['status'] === IncidentStatusEnum::Resolved->value) {
            $data['resolved_at'] = $data['resolved_at'] ?? now()->toDateTimeString();
        }

        $this->siteStatusService->createIncident($data);

        return redirect()
            ->route('admin.status.index')
            ->with('success', 'Incident created successfully.');
    }

    /**
     * @throws \Exception
     */
    public function edit(ServiceIncident $incident): View
    {
        $this->setAdminPrefs();

        $services = $this->siteStatusService->getAllServicesForAdmin();

        $this->viewData = array_merge($this->viewData, [
            'incident' => $incident->load(['services', 'creator']),
            'services' => $services,
            'meta_title' => 'Edit incident',
            'title' => 'Edit incident',
        ]);

        return view('admin.status.edit', $this->viewData);
    }

    public function update(UpdateIncidentRequest $request, ServiceIncident $incident): RedirectResponse
    {
        $data = $request->validated();

        if (($data['status'] ?? null) === IncidentStatusEnum::Resolved->value && empty($data['resolved_at'])) {
            $data['resolved_at'] = now()->toDateTimeString();
        }

        if (($data['status'] ?? null) !== IncidentStatusEnum::Resolved->value) {
            $data['resolved_at'] = null;
        }

        $this->siteStatusService->updateIncident($incident, $data);

        return redirect()
            ->route('admin.status.index')
            ->with('success', 'Incident updated successfully.');
    }

    public function resolve(ServiceIncident $incident): RedirectResponse
    {
        if ($incident->status === IncidentStatusEnum::Resolved) {
            return redirect()
                ->route('admin.status.index')
                ->with('info', 'Incident was already resolved.');
        }

        $this->siteStatusService->resolveIncident($incident);

        return redirect()
            ->route('admin.status.index')
            ->with('success', 'Incident marked as resolved.');
    }

    public function destroy(ServiceIncident $incident): RedirectResponse
    {
        $serviceIds = $incident->services()->pluck('id')->all();
        $incident->delete();

        foreach ($serviceIds as $serviceId) {
            $service = ServiceStatus::query()->find($serviceId);
            if ($service instanceof ServiceStatus) {
                $this->siteStatusService->recomputeServiceHealth($service);
            }
        }

        return redirect()
            ->route('admin.status.index')
            ->with('success', 'Incident deleted.');
    }

    public function updateService(UpdateServiceHealthRequest $request, ServiceStatus $service): RedirectResponse
    {
        $status = ServiceStatusEnum::from($request->validated('status'));
        $this->siteStatusService->updateServiceStatus($service, $status);

        return redirect()
            ->route('admin.status.index')
            ->with('success', 'Service status updated.');
    }
}
