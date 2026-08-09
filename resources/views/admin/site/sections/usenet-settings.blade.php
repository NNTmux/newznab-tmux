                <!-- Usenet Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Usenet Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="nzbsplitlevel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-folder-tree mr-1"></i>NZB File Path Level Deep
                            </label>
                            <input type="text" id="nzbsplitlevel" name="nzbsplitlevel" value="{{ $site['nzbsplitlevel'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Levels deep to store the nzb Files. <strong>If you change this you must run the misc/testing/DB/nzb-reorg script!</strong></p>
                        </div>

                        <div>
                            <label for="partretentionhours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-clock mr-1"></i>Part Retention Hours
                            </label>
                            <input type="text" id="partretentionhours" name="partretentionhours" value="{{ $site['partretentionhours'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of hours incomplete parts and binaries will be retained.</p>
                        </div>

                        <div>
                            <label for="releaseretentiondays" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-calendar-days mr-1"></i>Release Retention
                            </label>
                            <input type="text" id="releaseretentiondays" name="releaseretentiondays" value="{{ $site['releaseretentiondays'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of days releases will be retained for use throughout site. Set to 0 to disable.</p>
                        </div>

                        <div>
                            <label for="miscotherretentionhours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-hourglass mr-1"></i>Other->Misc Retention Hours
                            </label>
                            <input type="text" id="miscotherretentionhours" name="miscotherretentionhours" value="{{ $site['miscotherretentionhours'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of hours releases categorized as Misc->Other will be retained. Set to 0 to disable.</p>
                        </div>

                        <div>
                            <label for="mischashedretentionhours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-hashtag mr-1"></i>Other->Hashed Retention Hours
                            </label>
                            <input type="text" id="mischashedretentionhours" name="mischashedretentionhours" value="{{ $site['mischashedretentionhours'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of hours releases categorized as Misc->Hashed will be retained. Set to 0 to disable.</p>
                        </div>

                        <div>
                            <label for="partsdeletechunks" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-trash mr-1"></i>Parts Delete In Chunks
                            </label>
                            <input type="text" id="partsdeletechunks" name="partsdeletechunks" value="{{ $site['partsdeletechunks'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Default is 0 (off), which will remove parts in one go. If backfilling or importing and parts table is large, using chunks of 5000+ will speed up removal. Normal indexing is fastest with this setting at 0.</p>
                        </div>

                        <div>
                            <label for="minfilestoformrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-file-alt mr-1"></i>Minimum Files to Make a Release
                            </label>
                            <input type="text" id="minfilestoformrelease" name="minfilestoformrelease" value="{{ $site['minfilestoformrelease'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The minimum number of files to make a release. i.e. if set to two, then releases which only contain one file will not be created.</p>
                        </div>

                        <div>
                            <label for="minsizetoformrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-compress mr-1"></i>Minimum File Size to Make a Release
                            </label>
                            <input type="text" id="minsizetoformrelease" name="minsizetoformrelease" value="{{ $site['minsizetoformrelease'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The minimum total size in bytes to make a release. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="maxsizetoformrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-expand mr-1"></i>Maximum File Size to Make a Release
                            </label>
                            <input type="text" id="maxsizetoformrelease" name="maxsizetoformrelease" value="{{ $site['maxsizetoformrelease'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum total size in bytes to make a release. If set to 0, then ignored. Only deletes during release creation.</p>
                        </div>

                        <div>
                            <label for="completionpercent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-percentage mr-1"></i>Minimum Completion Percent
                            </label>
                            <input type="text" id="completionpercent" name="completionpercent" value="{{ $site['completionpercent'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The minimum completion percent to make a release. i.e. if set to 97, then releases under 97% completion will not be created. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="grabstatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-sync mr-1"></i>Update Grabs
                            </label>
                            <select id="grabstatus" name="grabstatus" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['grabstatus'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to update download counts when someone downloads a release.</p>
                        </div>

                        <div>
                            <label for="crossposttime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-clock mr-1"></i>Crossposted Time Check
                            </label>
                            <input type="text" id="crossposttime" name="crossposttime" value="{{ $site['crossposttime'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The time in hours to check for crossposted releases - this will delete 1 of the releases if the 2 are posted by the same person in the same time period.</p>
                        </div>

                        <div>
                            <label for="maxmssgs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-envelope mr-1"></i>Max Messages
                            </label>
                            <input type="text" id="maxmssgs" name="maxmssgs" value="{{ $site['maxmssgs'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of messages to fetch at a time from the server.</p>
                        </div>

                        <div>
                            <label for="max_headers_iteration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-list-ol mr-1"></i>Max Headers Iteration
                            </label>
                            <input type="text" id="max_headers_iteration" name="max_headers_iteration" value="{{ $site['max_headers_iteration'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of headers that update binaries sees as the total range. This ensures that a total of no more than this is attempted to be downloaded at one time per group.</p>
                        </div>

                        <div>
                            <label for="newgroupscanmethod" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-question-circle mr-1"></i>Where to Start New Groups
                            </label>
                            <select id="newgroupscanmethod" name="newgroupscanmethod" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['newgroupscanmethod'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $newgroupscan_names[$index] ?? $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="newgroupdaystoscan" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Days to Scan</label>
                                    <input type="text" id="newgroupdaystoscan" name="newgroupdaystoscan" value="{{ $site['newgroupdaystoscan'] ?? '' }}"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                                <div>
                                    <label for="newgroupmsgstoscan" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Posts to Scan</label>
                                    <input type="text" id="newgroupmsgstoscan" name="newgroupmsgstoscan" value="{{ $site['newgroupmsgstoscan'] ?? '' }}"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Scan back X (posts/days) for each new group? Can backfill to scan further.</p>
                        </div>

                        <div>
                            <label for="safebackfilldate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-calendar-alt mr-1"></i>Safe Backfill Date
                            </label>
                            <input type="text" id="safebackfilldate" name="safebackfilldate" value="{{ $site['safebackfilldate'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The target date for safe backfill. Format: YYYY-MM-DD</p>
                        </div>

                        <div>
                            <label for="disablebackfillgroup" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fas fa-power-off mr-1"></i>Auto Disable Groups During Backfill
                            </label>
                            <select id="disablebackfillgroup" name="disablebackfillgroup" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['disablebackfillgroup'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to disable a group automatically during backfill if the target date has been reached.</p>
                        </div>
                    </div>
                </div>
