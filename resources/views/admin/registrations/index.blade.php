@extends('layouts.admin')

@section('content')
@php
    use Illuminate\Support\Str;

    $statusBadgeClasses = static function (int $status): string {
        return match ($status) {
            \App\Models\Settings::REGISTER_STATUS_OPEN => 'border border-emerald-500/30 bg-emerald-600 text-white shadow-sm dark:border-emerald-300/20 dark:bg-emerald-500 dark:text-slate-950',
            \App\Models\Settings::REGISTER_STATUS_INVITE => 'border border-amber-500/30 bg-amber-500 text-slate-950 shadow-sm dark:border-amber-200/20 dark:bg-amber-400 dark:text-slate-950',
            default => 'border border-rose-500/30 bg-rose-600 text-white shadow-sm dark:border-rose-200/20 dark:bg-rose-500 dark:text-white',
        };
    };
    $periodStatusClasses = static function (bool $isEnabled): string {
        return $isEnabled
            ? 'border border-emerald-500/30 bg-emerald-600 text-white shadow-sm dark:border-emerald-300/20 dark:bg-emerald-500 dark:text-slate-950'
            : 'border border-slate-400/30 bg-slate-600 text-white shadow-sm dark:border-slate-300/20 dark:bg-slate-500 dark:text-slate-950';
    };
    $primaryButtonClasses = 'inline-flex items-center rounded-lg border border-blue-700 bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-blue-300/20 dark:bg-blue-500 dark:text-white dark:hover:bg-blue-400 dark:focus:ring-offset-gray-900';
    $secondaryButtonClasses = 'inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-900';
    $editButtonClasses = 'inline-flex items-center rounded-md border border-sky-700 bg-sky-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:border-sky-300/20 dark:bg-sky-500 dark:text-white dark:hover:bg-sky-400 dark:focus:ring-offset-gray-900';
    $toggleButtonClasses = 'inline-flex items-center rounded-md border border-amber-500/40 bg-amber-500 px-3 py-1.5 text-xs font-medium text-slate-950 shadow-sm transition hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:border-amber-200/20 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300 dark:focus:ring-offset-gray-900';
    $deleteButtonClasses = 'inline-flex items-center rounded-md border border-rose-700 bg-rose-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:border-rose-300/20 dark:bg-rose-500 dark:text-white dark:hover:bg-rose-400 dark:focus:ring-offset-gray-900';
@endphp

<div class="space-y-6">
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
            <p class="font-medium">Please fix the following issues:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-admin.card>
        <x-admin.page-header
            :title="$title"
            icon="fas fa-user-plus"
            subtitle="Manage the manual registration mode, schedule temporary open periods, and review recent registration activity."
        />

        <div class="grid grid-cols-1 gap-4 px-6 py-6 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Effective Status</p>
                <div class="mt-3 flex items-center gap-3">
                    <span class="inline-flex items-center justify-center rounded-full px-3 py-1.5 text-sm font-semibold {{ $statusBadgeClasses($registrationStatus['effective_status']) }}">
                        {{ $registrationStatus['effective_status_label'] }}
                    </span>
                    @if($registrationStatus['scheduled_override_active'])
                        <span class="inline-flex items-center justify-center rounded-full border border-blue-500/30 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm dark:border-blue-300/20 dark:bg-blue-500 dark:text-white">
                            Scheduled Override
                        </span>
                    @endif
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $registrationStatus['message'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Manual Baseline</p>
                <div class="mt-3">
                    <span class="inline-flex items-center justify-center rounded-full px-3 py-1.5 text-sm font-semibold {{ $statusBadgeClasses($registrationStatus['manual_status']) }}">
                        {{ $registrationStatus['manual_status_label'] }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    This is the fallback mode used whenever no scheduled open period is active.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Active Open Period</p>
                @if($registrationStatus['active_period'])
                    <div class="mt-3">
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $registrationStatus['active_period']->name }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $registrationStatus['active_period']->starts_at->format('Y-m-d H:i') }} to
                            {{ $registrationStatus['active_period']->ends_at->format('Y-m-d H:i') }}
                        </p>
                    </div>
                @else
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">No scheduled open-registration period is active right now.</p>
                @endif
            </div>
        </div>
    </x-admin.card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.card>
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Manual Status</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Change the baseline site registration mode. Scheduled open periods can temporarily override this selection.</p>
            </div>
            <form method="POST" action="{{ route('admin.registrations.update-status') }}" class="space-y-4 px-6 py-6">
                @csrf

                <x-form.group label="Manual Registration Status" for="registerstatus" help="Open allows public signups, Invite requires a valid invitation, and Closed blocks registrations entirely.">
                    <x-select id="registerstatus" name="registerstatus" class="w-full">
                        @foreach($statusOptions as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected($registrationStatus['manual_status'] === $statusValue)>{{ $statusLabel }}</option>
                        @endforeach
                    </x-select>
                </x-form.group>

                <x-form.group label="Audit Note" for="note" help="Optional context recorded in the registration status history.">
                    <textarea id="note"
                              name="note"
                              rows="3"
                              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                              placeholder="Example: opening signups for the weekend after maintenance.">{{ old('note') }}</textarea>
                </x-form.group>

                <button type="submit" class="{{ $primaryButtonClasses }}">
                    <i class="fas fa-save mr-2"></i>Save Manual Status
                </button>
            </form>
        </x-admin.card>

        <x-admin.card>
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $editingPeriod ? 'Edit Scheduled Open Period' : 'Create Scheduled Open Period' }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Scheduled periods temporarily make public registration open without changing the saved baseline mode.</p>
            </div>

            <form method="POST"
                  action="{{ $editingPeriod ? route('admin.registrations.periods.update', $editingPeriod) : route('admin.registrations.periods.store') }}"
                  class="space-y-4 px-6 py-6">
                @csrf
                @if($editingPeriod)
                    @method('PUT')
                @endif

                <x-form.group label="Period Name" for="name">
                    <x-input id="name"
                             name="name"
                             type="text"
                             value="{{ old('name', $editingPeriod?->name) }}"
                             class="w-full" />
                </x-form.group>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-form.group label="Starts At" for="starts_at">
                        <x-input id="starts_at"
                                 name="starts_at"
                                 type="datetime-local"
                                 value="{{ old('starts_at', $editingPeriod?->starts_at?->format('Y-m-d\TH:i')) }}"
                                 class="w-full" />
                    </x-form.group>

                    <x-form.group label="Ends At" for="ends_at">
                        <x-input id="ends_at"
                                 name="ends_at"
                                 type="datetime-local"
                                 value="{{ old('ends_at', $editingPeriod?->ends_at?->format('Y-m-d\TH:i')) }}"
                                 class="w-full" />
                    </x-form.group>
                </div>

                <x-form.group label="Notes" for="notes" help="Optional notes for the period itself. These appear in the admin page and history metadata.">
                    <textarea id="notes"
                              name="notes"
                              rows="3"
                              class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                              placeholder="Example: announce on Discord and monitor spam rate.">{{ old('notes', $editingPeriod?->notes) }}</textarea>
                </x-form.group>

                <label class="inline-flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox"
                           name="is_enabled"
                           value="1"
                           @checked(old('is_enabled', $editingPeriod?->is_enabled ?? true))
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                    Enable this period immediately
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="{{ $primaryButtonClasses }}">
                        <i class="fas fa-calendar-plus mr-2"></i>{{ $editingPeriod ? 'Update Period' : 'Create Period' }}
                    </button>

                    @if($editingPeriod)
                        <a href="{{ route('admin.registrations.index') }}"
                           class="{{ $secondaryButtonClasses }}">
                            <i class="fas fa-xmark mr-2"></i>Cancel Edit
                        </a>
                    @endif
                </div>
            </form>
        </x-admin.card>
    </div>

    <x-admin.card>
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Scheduled Open Periods</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage temporary windows when registrations should be publicly open.</p>
        </div>

        @if($periods->isEmpty())
            <div class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-calendar-xmark mb-3 text-3xl"></i>
                <p>No scheduled open-registration periods have been created yet.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Window</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Updated By</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        @foreach($periods as $period)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-6 py-4 align-top">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $period->name }}</p>
                                    @if($registrationStatus['active_period'] && $registrationStatus['active_period']->id === $period->id)
                                        <span class="mt-2 inline-flex items-center justify-center rounded-full border border-blue-500/30 bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm dark:border-blue-300/20 dark:bg-blue-500 dark:text-white">
                                            Active Right Now
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <span class="inline-flex min-w-20 items-center justify-center rounded-full px-3 py-1.5 text-xs font-semibold {{ $periodStatusClasses($period->is_enabled) }}">
                                        {{ $period->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                    <p>{{ $period->starts_at->format('Y-m-d H:i') }}</p>
                                    <p class="mt-1 text-gray-500 dark:text-gray-400">to {{ $period->ends_at->format('Y-m-d H:i') }}</p>
                                </td>
                                <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                    {{ $period->notes ? Str::limit($period->notes, 100) : '—' }}
                                </td>
                                <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                    {{ $period->updatedByUser?->username ?? $period->createdByUser?->username ?? 'System' }}
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('admin.registrations.index', ['edit_period' => $period->id]) }}"
                                           class="{{ $editButtonClasses }}">
                                            <i class="fas fa-pen mr-1.5"></i>Edit
                                        </a>

                                        <form method="POST" action="{{ route('admin.registrations.periods.toggle', $period) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="{{ $toggleButtonClasses }}">
                                                <i class="fas {{ $period->is_enabled ? 'fa-pause' : 'fa-play' }} mr-1.5"></i>{{ $period->is_enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.registrations.periods.destroy', $period) }}" onsubmit="return confirm('Delete this scheduled registration period?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="{{ $deleteButtonClasses }}">
                                                <i class="fas fa-trash mr-1.5"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.card>
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Registration History</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Recent manual status changes and scheduled-period actions.</p>
            </div>

            @if($history->isEmpty())
                <div class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                    <i class="fas fa-clock-rotate-left mb-3 text-3xl"></i>
                    <p>No registration history has been recorded yet.</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($history as $entry)
                        <div class="px-6 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $entry->description }}</p>
                                    @if(!empty($entry->metadata['note']))
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $entry->metadata['note'] }}</p>
                                    @endif
                                </div>
                                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $entry->created_at?->format('Y-m-d H:i:s') }}
                                </span>
                            </div>
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Changed by: {{ $entry->changedByUser?->username ?? 'System' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-admin.card>

        <x-admin.card>
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Registration Activity</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Successful signups come from `UserActivity`; failed attempts come from the dedicated registration log.</p>
            </div>

            <div class="grid grid-cols-1 divide-y divide-gray-200 dark:divide-gray-700 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                <div>
                    <div class="border-b border-gray-200 px-6 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Successful Signups</h3>
                    </div>
                    @forelse($recentSuccessfulRegistrations as $activity)
                        <div class="px-6 py-4">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $activity->username }}</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $activity->metadata['email'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>IP: {{ $activity->metadata['ip_address'] }}</span>
                                <span>{{ $activity->created_at?->format('Y-m-d H:i:s') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No successful registrations have been recorded recently.
                        </div>
                    @endforelse
                </div>

                <div>
                    <div class="border-b border-gray-200 px-6 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Failed Attempts</h3>
                    </div>
                    @forelse($recentFailedAttempts as $failure)
                        <div class="px-6 py-4">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $failure['reason'] ?? 'registration_failed' }}</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ $failure['email'] ?? 'Unknown email' }}
                                @if(!empty($failure['username']))
                                    <span class="text-gray-500 dark:text-gray-500">({{ $failure['username'] }})</span>
                                @endif
                            </p>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if(!empty($failure['ip']))
                                    <span>IP: {{ $failure['ip'] }}</span>
                                @endif
                                @if(!empty($failure['manual_registration_status_label']))
                                    <span>Manual: {{ $failure['manual_registration_status_label'] }}</span>
                                @endif
                                @if(!empty($failure['registration_status_label']))
                                    <span>Effective: {{ $failure['registration_status_label'] }}</span>
                                @endif
                                <span>{{ $failure['timestamp']->format('Y-m-d H:i:s') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No recent failed registration attempts were found in the registration log.
                        </div>
                    @endforelse
                </div>
            </div>
        </x-admin.card>
    </div>
</div>
@endsection
