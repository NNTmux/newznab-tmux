                <!-- Pending Role Information -->
                @if(!is_array($user))
                    @php
                        $allPendingRoles = $user->getAllPendingStackedRoles();
                    @endphp
                    @if($allPendingRoles->count() > 0)
                        <div class="border-2 border-primary-300 dark:border-primary-600 rounded-lg p-4 bg-linear-to-br from-primary-50 via-primary-50 to-white dark:from-primary-900/30 dark:via-primary-900/20 dark:to-gray-800 shadow-md">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-semibold text-link dark:text-primary-300 flex items-center">
                                    <i aria-hidden="true" class="fas fa-layer-group mr-2 text-lg"></i>
                                    Pending Stacked Role{{ $allPendingRoles->count() > 1 ? 's' : '' }}
                                    <span class="ml-2 px-2 py-0.5 bg-accent text-white text-xs rounded-full">{{ $allPendingRoles->count() }}</span>
                                </label>
                                <x-admin.badge tone="primary" icon="fas fa-clock" class="animate-pulse">SCHEDULED</x-admin.badge>
                            </div>

                            <div class="space-y-3">
                                @foreach($allPendingRoles as $index => $pendingRoleInfo)
                                    <div class="bg-white dark:bg-gray-700 rounded-md p-3 border border-primary-200 dark:border-primary-700 @if(!$loop->last) mb-2 @endif">
                                        <div class="flex items-center mb-2">
                                            <span class="flex items-center justify-center w-6 h-6 bg-accent text-white text-xs font-bold rounded-full mr-2">
                                                {{ $index + 1 }}
                                            </span>
                                            <span class="text-sm font-bold text-link dark:text-primary-300">{{ $pendingRoleInfo['role_name'] }}</span>
                                        </div>
                                        <div class="ml-8 space-y-1 text-sm">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                                    <i aria-hidden="true" class="fas fa-play-circle text-success-500 mr-1"></i>Starts:
                                                </span>
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $pendingRoleInfo['start_date']->format('M j, Y g:i A') }}</span>
                                            </div>
                                            @if($pendingRoleInfo['end_date'])
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                                        <i aria-hidden="true" class="fas fa-stop-circle text-danger-400 mr-1"></i>Ends:
                                                    </span>
                                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $pendingRoleInfo['end_date']->format('M j, Y g:i A') }}</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs text-gray-600 dark:text-gray-400">Time until activation:</span>
                                                <span class="text-sm font-semibold text-link dark:text-primary-400">{{ $pendingRoleInfo['start_date']->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-between mt-3">
                                <p class="text-xs text-link dark:text-primary-300">
                                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>
                                    These roles will automatically activate in sequence as each previous role expires
                                </p>
                                @if(!empty($user->pending_roles_id))
                                    <label class="inline-flex items-center px-3 py-2 rounded-md bg-danger-50 dark:bg-danger-900/30 border border-danger-200 dark:border-danger-700 hover:bg-danger-100 dark:hover:bg-danger-900/50 cursor-pointer transition-all">
                                        <input type="checkbox" name="cancel_pending_role" value="1"
                                               class="rounded border-danger-300 dark:border-danger-600 bg-white dark:bg-gray-700 text-danger-600 dark:text-danger-500 shadow-sm focus:ring-2 focus:ring-danger-500 dark:focus:ring-danger-400 focus:border-danger-500 dark:focus:border-danger-400 cursor-pointer">
                                        <span class="ml-2 text-xs text-danger-700 dark:text-danger-300 font-semibold">Cancel all pending roles</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                    @elseif(!empty($user->pending_roles_id) && !empty($user->pending_role_start_date))
                        {{-- Fallback to old display if no history records but pending_roles_id is set --}}
                        <div class="border-2 border-primary-300 dark:border-primary-600 rounded-lg p-4 bg-linear-to-br from-primary-50 via-primary-50 to-white dark:from-primary-900/30 dark:via-primary-900/20 dark:to-gray-800 shadow-md">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-semibold text-link dark:text-primary-300 flex items-center">
                                    <i aria-hidden="true" class="fas fa-layer-group mr-2 text-lg"></i>
                                    Pending Stacked Role
                                </label>
                                <x-admin.badge tone="primary" icon="fas fa-clock" class="animate-pulse">SCHEDULED</x-admin.badge>
                            </div>

                            @php
                                $pendingRole = $user->pendingRole;
                                $pendingStartDate = \Carbon\Carbon::parse($user->pending_role_start_date);
                                $daysUntilActivation = $pendingStartDate->diffInDays(now());
                            @endphp

                            <div class="bg-white dark:bg-gray-700 rounded-md p-3 mb-3 border border-primary-200 dark:border-primary-700">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Will change to:</span>
                                    <span class="text-sm font-bold text-link dark:text-primary-300">{{ $pendingRole->name ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Activation date:</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $pendingStartDate->format('M j, Y g:i A') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Time until activation:</span>
                                    <span class="text-sm font-semibold text-link dark:text-primary-400">{{ $pendingStartDate->diffForHumans(['parts' => 2]) }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-xs text-link dark:text-primary-300">
                                    <i aria-hidden="true" class="fas fa-info-circle mr-1"></i>
                                    This role will automatically activate when the current role expires
                                </p>
                                <label class="inline-flex items-center px-3 py-2 rounded-md bg-danger-50 dark:bg-danger-900/30 border border-danger-200 dark:border-danger-700 hover:bg-danger-100 dark:hover:bg-danger-900/50 cursor-pointer transition-all">
                                    <input type="checkbox" name="cancel_pending_role" value="1"
                                           class="rounded border-danger-300 dark:border-danger-600 bg-white dark:bg-gray-700 text-danger-600 dark:text-danger-500 shadow-sm focus:ring-2 focus:ring-danger-500 dark:focus:ring-danger-400 focus:border-danger-500 dark:focus:border-danger-400 cursor-pointer">
                                    <span class="ml-2 text-xs text-danger-700 dark:text-danger-300 font-semibold">Cancel pending role</span>
                                </label>
                            </div>
                        </div>
                    @endif
                @endif
