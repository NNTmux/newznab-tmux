@extends('layouts.admin')

{{-- Styles moved to resources/css/csp-safe.css --}}

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800">
                <i class="fa fa-terminal mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Success Message -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-green-800 dark:text-green-300">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        <!-- Tmux Settings Form -->
        <form method="post" action="{{ url('admin/tmux-edit') }}" class="p-6" id="tmuxForm">
            @csrf
            <input type="hidden" name="action" value="submit">


            <div class="space-y-8">
                <!-- Tmux - How It Works -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Tmux - How It Works</h2>
                    <div class="bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 rounded-lg p-4">
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">Tmux is a screen multiplexer and at least version 1.6 is required. It is used here to allow multiple windows per session and multiple panes per window.</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">Each script is run in its own shell environment. It is not looped, but allowed to run once and then exit. This notifies tmux that the pane is dead and can then be respawned with another iteration of the script in a new shell environment.</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">This allows for scripts that crash to be restarted without user intervention.</p>
                        <div class="bg-yellow-50 dark:bg-gray-800 border border-yellow-300 dark:border-gray-600 rounded p-3 mt-3">
                            <p class="text-sm font-medium text-yellow-800 dark:text-gray-300"><i class="fa fa-exclamation-triangle mr-2"></i>NOTICE:</p>
                            <p class="text-sm text-yellow-700 dark:text-gray-400">If "Save Tmux Settings" is the last thing you did on this page, refreshing will save the current form values again, not reload from database.</p>
                        </div>
                    </div>
                </div>

                <!-- Monitor Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Monitor Settings</h2>
                    <div class="space-y-4">
                        <x-form.group label="Tmux Scripts Running" for="running" help="Shutdown switch. When on, scripts run; when off, all scripts are terminated.">
                            <x-select id="running" name="running" class="w-full">
                                @foreach($yesno_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['running'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Monitor Loop Timer" for="monitor_delay" help="Time between query refreshes. Lower = more frequent DB queries.">
                            <div class="flex gap-2">
                                <x-input id="monitor_delay" name="monitor_delay" type="number" value="{{ $site['monitor_delay'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <x-form.group label="Tmux Session Name" for="tmux_session" help="Session name for tmux. No spaces allowed. Can't be changed after scripts start.">
                            <x-input id="tmux_session" name="tmux_session" type="text" value="{{ $site['tmux_session'] ?? 'nntmux' }}" class="w-full" />
                        </x-form.group>

                        <x-form.group label="Process Niceness" for="niceness" help="Process priority. Lower values = higher priority. Range: -20 (highest) to 19 (lowest). Default: 10.">
                            <x-input id="niceness" name="niceness" type="number" min="-20" max="19" value="{{ $site['niceness'] ?? 10 }}" class="w-full" />
                        </x-form.group>
                    </div>
                </div>

                <!-- Sequential Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Sequential Settings</h2>
                    <div class="space-y-4">
                        <x-form.group label="Run Sequential" for="sequential" help="Sequential runs update_binaries, backfill and update releases sequentially.">
                            <x-select id="sequential" name="sequential" class="w-full">
                                @foreach($sequential_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['sequential'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $sequential_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Sequential Sleep Timer" for="seq_timer">
                            <div class="flex gap-2">
                                <x-input id="seq_timer" name="seq_timer" type="number" value="{{ $site['seq_timer'] ?? 60 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <div class="bg-yellow-50 dark:bg-gray-800 border border-yellow-300 dark:border-gray-600 rounded p-3">
                            <p class="text-sm text-yellow-700 dark:text-gray-400"><i class="fa fa-exclamation-triangle mr-2"></i>Sequential mode is not recommended as it's not tested enough.</p>
                        </div>
                    </div>
                </div>

                <!-- Update Binaries Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Update Binaries Settings</h2>
                    <div class="space-y-4">
                        <x-form.group label="Update Binaries" for="binaries" help="Gets from your last_record to now.">
                            <x-select id="binaries" name="binaries" class="w-full">
                                @foreach($binaries_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['binaries'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $binaries_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Update Binaries Sleep Timer" for="bins_timer">
                            <div class="flex gap-2">
                                <x-input id="bins_timer" name="bins_timer" type="number" value="{{ $site['bins_timer'] ?? 10 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <x-form.group label="Binaries Kill Timer" for="bins_kill_timer" help="Time allowed to run with no updates.">
                            <div class="flex gap-2">
                                <x-input id="bins_kill_timer" name="bins_kill_timer" type="number" value="{{ $site['bins_kill_timer'] ?? 30 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">minutes</span>
                            </div>
                        </x-form.group>
                    </div>
                </div>

                <!-- Backfill Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Backfill Settings</h2>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-form.group label="Backfill Mode" for="backfill">
                                <x-select id="backfill" name="backfill" class="w-full">
                                    @foreach($backfill_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['backfill'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $backfill_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>

                            <x-form.group label="Backfill Order" for="backfill_order">
                                <x-select id="backfill_order" name="backfill_order" class="w-full">
                                    @foreach($backfill_group_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['backfill_order'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $backfill_group[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>

                            <x-form.group label="Backfill Days" for="backfill_days">
                                <x-select id="backfill_days" name="backfill_days" class="w-full">
                                    @foreach($backfill_days_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['backfill_days'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $backfill_days[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>
                        </div>

                        <x-form.group label="Backfill Quantity" for="backfill_qty" help="Number of headers per group per thread to download.">
                            <x-input id="backfill_qty" name="backfill_qty" type="number" value="{{ $site['backfill_qty'] ?? 20000 }}" class="w-full" />
                        </x-form.group>

                        <x-form.group label="Backfill Groups" for="backfill_groups" help="Number of groups to backfill per loop.">
                            <x-input id="backfill_groups" name="backfill_groups" type="number" value="{{ $site['backfill_groups'] ?? 1 }}" class="w-full" />
                        </x-form.group>

                        <x-form.group label="Backfill Sleep Timer" for="back_timer">
                            <div class="flex gap-2">
                                <x-input id="back_timer" name="back_timer" type="number" value="{{ $site['back_timer'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <x-form.group label="Variable Sleep Timer" for="progressive" help="Vary backfill sleep depending on collection count.">
                            <x-select id="progressive" name="progressive" class="w-full">
                                @foreach($yesno_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['progressive'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>
                    </div>
                </div>

                <!-- Update Releases Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Update Releases Settings</h2>
                    <div class="space-y-4">
                        <x-form.group label="Update Releases" for="releases" help="Create releases. Only turn off when you only want to post process.">
                            <x-select id="releases" name="releases" class="w-full">
                                @foreach($releases_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['releases'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $releases_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Update Releases Sleep Timer" for="rel_timer">
                            <div class="flex gap-2">
                                <x-input id="rel_timer" name="rel_timer" type="number" value="{{ $site['rel_timer'] ?? 15 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>
                    </div>
                </div>

                <!-- Postprocessing Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Postprocessing Settings</h2>
                    <div class="space-y-4">
                        <x-form.group label="Postprocess Additional" for="post" help="Deep rar inspection, preview/sample creation, NFO processing.">
                            <x-select id="post" name="post" class="w-full">
                                @foreach($post_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['post'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $post_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Postprocess Additional Sleep Timer" for="post_timer">
                            <div class="flex gap-2">
                                <x-input id="post_timer" name="post_timer" type="number" value="{{ $site['post_timer'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <x-form.group label="Postprocess Kill Timer" for="post_kill_timer" help="Time allowed with no screen updates.">
                            <div class="flex gap-2">
                                <x-input id="post_kill_timer" name="post_kill_timer" type="number" value="{{ $site['post_kill_timer'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <x-form.group label="Postprocess Amazon" for="post_amazon" help="Books, music and games lookups.">
                            <x-select id="post_amazon" name="post_amazon" class="w-full">
                                @foreach($yesno_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['post_amazon'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Postprocess Amazon Sleep Timer" for="post_timer_amazon">
                            <div class="flex gap-2">
                                <x-input id="post_timer_amazon" name="post_timer_amazon" type="number" value="{{ $site['post_timer_amazon'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>

                        <x-form.group label="Postprocess Non-Amazon" for="post_non" help="Movies, anime and TV lookups.">
                            <x-select id="post_non" name="post_non" class="w-full">
                                @foreach($yesno_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['post_non'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Postprocess Non-Amazon Sleep Timer" for="post_timer_non">
                            <div class="flex gap-2">
                                <x-input id="post_timer_non" name="post_timer_non" type="number" value="{{ $site['post_timer_non'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>
                    </div>
                </div>

                <!-- Fix Release Names -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Fix Release Names</h2>
                    <div class="space-y-4">
                        <x-form.group label="Fix Release Names" for="fix_names" help="Fix release names using NFOs, par2 files, filenames, md5 and sha1.">
                            <x-select id="fix_names" name="fix_names" class="w-full">
                                @foreach($yesno_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['fix_names'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <x-form.group label="Fix Release Names Sleep Timer" for="fix_timer">
                            <div class="flex gap-2">
                                <x-input id="fix_timer" name="fix_timer" type="number" value="{{ $site['fix_timer'] ?? 60 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>
                    </div>
                </div>

                <!-- Remove Crap Releases -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Remove Crap Releases</h2>
                    <div class="space-y-4">
                        <x-form.group label="Remove Crap Releases" for="fix_crap_opt" help="Remove passworded and other junk releases.">
                            <div class="space-y-2">
                                @foreach($fix_crap_radio_ids as $index => $val)
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="radio"
                                               id="fix_crap_opt_{{ $val }}"
                                               name="fix_crap_opt"
                                               value="{{ $val }}"
                                               {{ ($site['fix_crap_opt'] ?? 'Disabled') == $val ? 'checked' : '' }}
                                               class="form-radio h-4 w-4 text-blue-600 dark:bg-gray-700 dark:border-gray-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $fix_crap_radio_names[$index] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-form.group>

                        <div id="crap_types_container">
                            <x-form.group label="Select Crap Types" for="fix_crap_types" help="Choose which types of crap releases to remove.">
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-blue-200 dark:border-blue-700">
                                    @php
                                        $selectedTypes = explode(',', $site['fix_crap'] ?? '');
                                    @endphp
                                    @foreach($fix_crap_check_ids as $index => $val)
                                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-2 rounded transition-colors">
                                            <input type="checkbox"
                                                   name="fix_crap[]"
                                                   value="{{ $val }}"
                                                   {{ in_array($val, $selectedTypes) ? 'checked' : '' }}
                                                   class="form-checkbox h-4 w-4 text-blue-600 rounded dark:bg-gray-700 dark:border-gray-600">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ ucfirst($fix_crap_check_names[$index]) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </x-form.group>
                        </div>

                        <x-form.group label="Remove Crap Sleep Timer" for="crap_timer">
                            <div class="flex gap-2">
                                <x-input id="crap_timer" name="crap_timer" type="number" value="{{ $site['crap_timer'] ?? 300 }}" class="flex-1" />
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md text-sm">seconds</span>
                            </div>
                        </x-form.group>
                    </div>
                </div>


                <!-- Console & Monitoring Tools -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Console & Monitoring Tools</h2>
                    <div class="bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 rounded-lg p-4 mb-4">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <i class="fa fa-info-circle mr-2"></i>
                            Enable monitoring tools to display system metrics in separate tmux windows. Each tool requires the corresponding package to be installed on your system.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <!-- Shell Console -->
                        <x-form.group label="Shell Console" for="console" help="Enable an interactive bash shell window for manual commands.">
                            <x-select id="console" name="console" class="w-full">
                                @foreach($yesno_ids as $index => $val)
                                    <option value="{{ $val }}" {{ ($site['console'] ?? '') == $val ? 'selected' : '' }}>
                                        {{ $yesno_names[$index] }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form.group>

                        <!-- System Monitoring Tools -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <x-form.group label="htop" for="htop" help="Interactive process viewer (apt-get install htop).">
                                <x-select id="htop" name="htop" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['htop'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>

                            <x-form.group label="mytop" for="mytop" help="MySQL/MariaDB performance monitor (apt-get install mytop).">
                                <x-select id="mytop" name="mytop" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['mytop'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>

                            <x-form.group label="nmon" for="nmon" help="System performance monitor (apt-get install nmon).">
                                <x-select id="nmon" name="nmon" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['nmon'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>
                        </div>

                        <!-- Network Monitoring Tools -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <x-form.group label="vnstat" for="vnstat" help="Network traffic monitor (apt-get install vnstat).">
                                <x-select id="vnstat" name="vnstat" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['vnstat'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>

                            <x-form.group label="vnstat Args" for="vnstat_args" help="Arguments for vnstat command (e.g., -i eth0).">
                                <x-input id="vnstat_args" name="vnstat_args" type="text" value="{{ $site['vnstat_args'] ?? '' }}" class="w-full" />
                            </x-form.group>

                            <x-form.group label="tcptrack" for="tcptrack" help="TCP connection monitor (apt-get install tcptrack).">
                                <x-select id="tcptrack" name="tcptrack" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['tcptrack'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>
                        </div>

                        <x-form.group label="tcptrack Args" for="tcptrack_args" help="Arguments for tcptrack (e.g., -i eth0).">
                            <x-input id="tcptrack_args" name="tcptrack_args" type="text" value="{{ $site['tcptrack_args'] ?? '' }}" class="w-full" />
                        </x-form.group>

                        <!-- Additional Tools -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-form.group label="bwm-ng" for="bwmng" help="Bandwidth monitor (apt-get install bwm-ng).">
                                <x-select id="bwmng" name="bwmng" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['bwmng'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>

                            <x-form.group label="Redis Stats" for="redis" help="Redis statistics monitor (requires redis-stat gem).">
                                <x-select id="redis" name="redis" class="w-full">
                                    @foreach($yesno_ids as $index => $val)
                                        <option value="{{ $val }}" {{ ($site['redis'] ?? '') == $val ? 'selected' : '' }}>
                                            {{ $yesno_names[$index] }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-form.group>
                        </div>

                        <div class="bg-yellow-50 dark:bg-gray-800 border border-yellow-300 dark:border-gray-600 rounded p-3">
                            <p class="text-sm text-yellow-700 dark:text-gray-400">
                                <i class="fa fa-exclamation-triangle mr-2"></i>
                                Note: Enabling a monitoring tool without having it installed will cause the tmux window to fail. Install required packages before enabling them.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <x-button type="submit" class="px-6 py-2">
                        <i class="fa fa-save mr-2"></i>Save Tmux Settings
                    </x-button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

