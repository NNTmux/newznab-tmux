@extends('layouts.admin')

@section('content')

<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800">
                <i class="fa fa-cog mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-green-800">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(!empty($error))
            <div class="mx-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-red-800">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ $error }}
                </p>
            </div>
        @endif

        <!-- Site Settings Form -->
        <form method="post" action="{{ url('admin/site-edit') }}" class="p-6">
            @csrf
            <input type="hidden" name="action" value="submit">

            <div class="space-y-8">
                <!-- Main Site Settings, HTML Layout, Tags -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Main Site Settings, HTML Layout, Tags</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="strapline" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-quote-right mr-1"></i>Strapline
                            </label>
                            <input type="text" id="strapline" name="strapline" value="{{ $site['strapline'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Displayed in the header on every public page.</p>
                        </div>

                        <div>
                            <label for="metatitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-heading mr-1"></i>Meta Title
                            </label>
                            <input type="text" id="metatitle" name="metatitle" value="{{ $site['metatitle'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Stem meta-tag appended to all page title tags.</p>
                        </div>

                        <div>
                            <label for="metadescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-comment mr-1"></i>Meta Description
                            </label>
                            <textarea id="metadescription" name="metadescription" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['metadescription'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Stem meta-description appended to all page meta description tags.</p>
                        </div>

                        <div>
                            <label for="metakeywords" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tags mr-1"></i>Meta Keywords
                            </label>
                            <textarea id="metakeywords" name="metakeywords" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['metakeywords'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Stem meta-keywords appended to all page meta keyword tags.</p>
                        </div>

                        <div>
                            <label for="footer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-copyright mr-1"></i>Footer
                            </label>
                            <textarea id="footer" name="footer" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['footer'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Displayed in the footer section of every public page.</p>
                        </div>

                        <div>
                            <label for="home_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-home mr-1"></i>Default Home Page
                            </label>
                            <input type="text" id="home_link" name="home_link" value="{{ $site['home_link'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The relative path to the landing page shown when a user logs in, or clicks the home link.</p>
                        </div>

                        <div>
                            <label for="dereferrer_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-external-link-alt mr-1"></i>Dereferrer Link
                            </label>
                            <input type="text" id="dereferrer_link" name="dereferrer_link" value="{{ $site['dereferrer_link'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Optional URL to prepend to external links.</p>
                        </div>

                        <div>
                            <label for="tandc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-gavel mr-1"></i>Terms and Conditions
                            </label>
                            <textarea id="tandc" name="tandc" rows="15"
                                      data-tinymce-api-key="{{ config('tinymce.api_key', 'no-api-key') }}"
                                      class="tinymce-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['tandc'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Text displayed in the terms and conditions page. Use the rich text editor to format your content.</p>
                        </div>
                    </div>
                </div>

                <!-- Usenet Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Usenet Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="nzbsplitlevel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-folder-tree mr-1"></i>NZB File Path Level Deep
                            </label>
                            <input type="text" id="nzbsplitlevel" name="nzbsplitlevel" value="{{ $site['nzbsplitlevel'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Levels deep to store the nzb Files. <strong>If you change this you must run the misc/testing/DB/nzb-reorg script!</strong></p>
                        </div>

                        <div>
                            <label for="partretentionhours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-clock mr-1"></i>Part Retention Hours
                            </label>
                            <input type="text" id="partretentionhours" name="partretentionhours" value="{{ $site['partretentionhours'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of hours incomplete parts and binaries will be retained.</p>
                        </div>

                        <div>
                            <label for="releaseretentiondays" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-calendar-days mr-1"></i>Release Retention
                            </label>
                            <input type="text" id="releaseretentiondays" name="releaseretentiondays" value="{{ $site['releaseretentiondays'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of days releases will be retained for use throughout site. Set to 0 to disable.</p>
                        </div>

                        <div>
                            <label for="miscotherretentionhours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-hourglass mr-1"></i>Other->Misc Retention Hours
                            </label>
                            <input type="text" id="miscotherretentionhours" name="miscotherretentionhours" value="{{ $site['miscotherretentionhours'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of hours releases categorized as Misc->Other will be retained. Set to 0 to disable.</p>
                        </div>

                        <div>
                            <label for="mischashedretentionhours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-hashtag mr-1"></i>Other->Hashed Retention Hours
                            </label>
                            <input type="text" id="mischashedretentionhours" name="mischashedretentionhours" value="{{ $site['mischashedretentionhours'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of hours releases categorized as Misc->Hashed will be retained. Set to 0 to disable.</p>
                        </div>

                        <div>
                            <label for="partsdeletechunks" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-trash mr-1"></i>Parts Delete In Chunks
                            </label>
                            <input type="text" id="partsdeletechunks" name="partsdeletechunks" value="{{ $site['partsdeletechunks'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Default is 0 (off), which will remove parts in one go. If backfilling or importing and parts table is large, using chunks of 5000+ will speed up removal. Normal indexing is fastest with this setting at 0.</p>
                        </div>

                        <div>
                            <label for="minfilestoformrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-alt mr-1"></i>Minimum Files to Make a Release
                            </label>
                            <input type="text" id="minfilestoformrelease" name="minfilestoformrelease" value="{{ $site['minfilestoformrelease'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The minimum number of files to make a release. i.e. if set to two, then releases which only contain one file will not be created.</p>
                        </div>

                        <div>
                            <label for="minsizetoformrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-compress mr-1"></i>Minimum File Size to Make a Release
                            </label>
                            <input type="text" id="minsizetoformrelease" name="minsizetoformrelease" value="{{ $site['minsizetoformrelease'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The minimum total size in bytes to make a release. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="maxsizetoformrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-expand mr-1"></i>Maximum File Size to Make a Release
                            </label>
                            <input type="text" id="maxsizetoformrelease" name="maxsizetoformrelease" value="{{ $site['maxsizetoformrelease'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum total size in bytes to make a release. If set to 0, then ignored. Only deletes during release creation.</p>
                        </div>

                        <div>
                            <label for="completionpercent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-percentage mr-1"></i>Minimum Completion Percent
                            </label>
                            <input type="text" id="completionpercent" name="completionpercent" value="{{ $site['completionpercent'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The minimum completion percent to make a release. i.e. if set to 97, then releases under 97% completion will not be created. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="grabstatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-sync mr-1"></i>Update Grabs
                            </label>
                            <select id="grabstatus" name="grabstatus" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
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
                                <i class="fa fa-clock mr-1"></i>Crossposted Time Check
                            </label>
                            <input type="text" id="crossposttime" name="crossposttime" value="{{ $site['crossposttime'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The time in hours to check for crossposted releases - this will delete 1 of the releases if the 2 are posted by the same person in the same time period.</p>
                        </div>

                        <div>
                            <label for="maxmssgs" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-envelope mr-1"></i>Max Messages
                            </label>
                            <input type="text" id="maxmssgs" name="maxmssgs" value="{{ $site['maxmssgs'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of messages to fetch at a time from the server.</p>
                        </div>

                        <div>
                            <label for="max_headers_iteration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-list-ol mr-1"></i>Max Headers Iteration
                            </label>
                            <input type="text" id="max_headers_iteration" name="max_headers_iteration" value="{{ $site['max_headers_iteration'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of headers that update binaries sees as the total range. This ensures that a total of no more than this is attempted to be downloaded at one time per group.</p>
                        </div>

                        <div>
                            <label for="newgroupscanmethod" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-question-circle mr-1"></i>Where to Start New Groups
                            </label>
                            <select id="newgroupscanmethod" name="newgroupscanmethod" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2">
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
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                                <div>
                                    <label for="newgroupmsgstoscan" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Posts to Scan</label>
                                    <input type="text" id="newgroupmsgstoscan" name="newgroupmsgstoscan" value="{{ $site['newgroupmsgstoscan'] ?? '' }}"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Scan back X (posts/days) for each new group? Can backfill to scan further.</p>
                        </div>

                        <div>
                            <label for="safebackfilldate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-calendar-alt mr-1"></i>Safe Backfill Date
                            </label>
                            <input type="text" id="safebackfilldate" name="safebackfilldate" value="{{ $site['safebackfilldate'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The target date for safe backfill. Format: YYYY-MM-DD</p>
                        </div>

                        <div>
                            <label for="disablebackfillgroup" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-power-off mr-1"></i>Auto Disable Groups During Backfill
                            </label>
                            <select id="disablebackfillgroup" name="disablebackfillgroup" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
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

                <!-- Lookup Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Lookup Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="lookuptv" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tv mr-1"></i>Lookup TV
                            </label>
                            <select id="lookuptv" name="lookuptv" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookuptv['ids'] as $index => $lookuptvId)
                                    <option value="{{ $lookuptvId }}" {{ ($site['lookuptv'] ?? '') == $lookuptvId ? 'selected' : '' }}>
                                        {{ $lookuptv['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup TV related ids on the web.</p>
                        </div>

                        <div>
                            <label for="lookupbooks" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-book mr-1"></i>Lookup Books
                            </label>
                            <select id="lookupbooks" name="lookupbooks" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupbooks['ids'] as $index => $lookupbooksId)
                                    <option value="{{ $lookupbooksId }}" {{ ($site['lookupbooks'] ?? '') == $lookupbooksId ? 'selected' : '' }}>
                                        {{ $lookupbooks['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup book information from Amazon.</p>
                        </div>

                        <div>
                            <label for="book_reqids" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-list mr-1"></i>Type of Books to Look Up
                            </label>
                            <select id="book_reqids" name="book_reqids[]" multiple class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" size="4">
                                @foreach($book_reqids['ids'] as $index => $bookReqId)
                                    <option value="{{ $bookReqId }}" {{ in_array($bookReqId, $book_reqids['selected'] ?? []) ? 'selected' : '' }}>
                                        {{ $book_reqids['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Categories of Books to lookup information for (only work if Lookup Books is set to yes).</p>
                        </div>

                        <div>
                            <label for="lookupimdb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-film mr-1"></i>Lookup Movies
                            </label>
                            <select id="lookupimdb" name="lookupimdb" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupmovies['ids'] as $index => $lookupmoviesId)
                                    <option value="{{ $lookupmoviesId }}" {{ ($site['lookupimdb'] ?? '') == $lookupmoviesId ? 'selected' : '' }}>
                                        {{ $lookupmovies['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup film information from IMDB or TheMovieDB.</p>
                        </div>

                        <div>
                            <label for="lookuplanguage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-language mr-1"></i>Movie Lookup Language
                            </label>
                            <select id="lookuplanguage" name="lookuplanguage" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookuplanguage['iso'] as $index => $languageIso)
                                    <option value="{{ $languageIso }}" {{ ($site['lookuplanguage'] ?? '') == $languageIso ? 'selected' : '' }}>
                                        {{ $lookuplanguage['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Preferred language for scraping external sources.</p>
                        </div>

                        <div>
                            <label for="lookupanidb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-dragon mr-1"></i>Lookup AniDB
                            </label>
                            <select id="lookupanidb" name="lookupanidb" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['lookupanidb'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup anime information from AniDB when processing binaries.</p>
                        </div>

                        <div>
                            <label for="lookupmusic" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-music mr-1"></i>Lookup Music
                            </label>
                            <select id="lookupmusic" name="lookupmusic" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupmusic['ids'] as $index => $lookupmusicId)
                                    <option value="{{ $lookupmusicId }}" {{ ($site['lookupmusic'] ?? '') == $lookupmusicId ? 'selected' : '' }}>
                                        {{ $lookupmusic['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup music information from Amazon.</p>
                        </div>

                        <div>
                            <label for="saveaudiopreview" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-music mr-1"></i>Save Audio Preview
                            </label>
                            <select id="saveaudiopreview" name="saveaudiopreview" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['saveaudiopreview'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to save a preview of an audio release (requires deep rar inspection enabled).<br>It is advisable to specify a path to the lame binary to reduce the size of audio previews.</p>
                        </div>

                        <div>
                            <label for="lookupgames" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-gamepad mr-1"></i>Lookup Games
                            </label>
                            <select id="lookupgames" name="lookupgames" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($lookupgames['ids'] as $index => $lookupgamesId)
                                    <option value="{{ $lookupgamesId }}" {{ ($site['lookupgames'] ?? '') == $lookupgamesId ? 'selected' : '' }}>
                                        {{ $lookupgames['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup game information from Amazon.</p>
                        </div>

                        <div>
                            <label for="lookupxxx" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-video mr-1"></i>Lookup XXX
                            </label>
                            <select id="lookupxxx" name="lookupxxx" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['lookupxxx'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to lookup XXX information when processing binaries.</p>
                        </div>
                    </div>
                </div>

                <!-- Language/Categorization Options -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Language/Categorization Options</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="categorizeforeign" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-globe mr-1"></i>Categorize Foreign
                            </label>
                            <select id="categorizeforeign" name="categorizeforeign" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['categorizeforeign'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to send foreign movies/tv to foreign sections or not. If set to true they will go in foreign categories.</p>
                        </div>

                        <div>
                            <label for="catwebdl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-cloud-download-alt mr-1"></i>Categorize WEB-DL
                            </label>
                            <select id="catwebdl" name="catwebdl" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['catwebdl'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to send WEB-DL to the WEB-DL section or not. If set to true they will go in WEB-DL category, false will send them in HD TV. This will also make them inaccessible to Sickbeard and possibly Couchpotato.</p>
                        </div>
                    </div>
                </div>

                <!-- Registration Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">User Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="registerstatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-user-plus mr-1"></i>Registration Status
                            </label>
                            <select id="registerstatus" name="registerstatus" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($registerstatus['ids'] as $index => $statusId)
                                    <option value="{{ $statusId }}" {{ ($site['registerstatus'] ?? '') == $statusId ? 'selected' : '' }}>
                                        {{ $registerstatus['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">The status of registrations to the site.</p>
                        </div>

                        <div>
                            <label for="userdownloadpurgedays" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-calendar mr-1"></i>User Downloads Purge Days
                            </label>
                            <input type="text" id="userdownloadpurgedays" name="userdownloadpurgedays" value="{{ $site['userdownloadpurgedays'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of days to preserve user download history, for use when checking limits being hit. Set to zero will remove all records of what users download, but retain history of when, so that role based limits can still be applied.</p>
                        </div>

                        <div>
                            <label for="userhostexclusion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-shield mr-1"></i>IP Whitelist
                            </label>
                            <input type="text" id="userhostexclusion" name="userhostexclusion" value="{{ $site['userhostexclusion'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">A comma separated list of IP addresses which will be excluded from user limits on number of requests and downloads per IP address. Include values for google reader and other shared services which may be being used.</p>
                        </div>
                    </div>
                </div>


                <!-- Password Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Password Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-archive mr-1"></i>Download Last Compressed File
                            </label>
                            <select id="end" name="end" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['end'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Try to download the last rar or zip file? (This is good if most of the files are at the end.) Note: The first rar/zip is still downloaded.</p>
                        </div>

                        <div>
                            <label for="showpasswordedrelease" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-lock mr-1"></i>Show Passworded Releases
                            </label>
                            <select id="showpasswordedrelease" name="showpasswordedrelease" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($passworded['ids'] as $index => $passwordedId)
                                    <option value="{{ $passwordedId }}" {{ ($site['showpasswordedrelease'] ?? '') == $passwordedId ? 'selected' : '' }}>
                                        {{ $passworded['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to show passworded releases in browse, search, api and rss feeds.</p>
                        </div>
                    </div>
                </div>

                <!-- Additional Usenet Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Additional Usenet Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="maxsizetopostprocess" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-archive mr-1"></i>Maximum Release Size to Post Process
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxsizetopostprocess" name="maxsizetopostprocess" value="{{ $site['maxsizetopostprocess'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">GB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum size in gigabytes to postprocess a release. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="minsizetopostprocess" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-archive mr-1"></i>Minimum Release Size to Post Process
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="minsizetopostprocess" name="minsizetopostprocess" value="{{ $site['minsizetopostprocess'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">MB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The minimum size in megabytes to post process (additional) a release. If set to 0, then ignored.</p>
                        </div>
                    </div>
                </div>

                <!-- Advanced Settings - For advanced users -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced Settings - For Advanced Users</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="maxnzbsprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-code mr-1"></i>Maximum NZBs Stage5
                            </label>
                            <input type="text" id="maxnzbsprocessed" name="maxnzbsprocessed" value="{{ $site['maxnzbsprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of NZB files to create on stage 5 at a time in update_releases. If more are to be created it will loop stage 5 until none remain.</p>
                        </div>

                        <div>
                            <label for="partrepair" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-toolbox mr-1"></i>Part Repair
                            </label>
                            <select id="partrepair" name="partrepair" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['partrepair'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to repair parts or not, increases backfill/binaries updating time.</p>
                        </div>

                        <div>
                            <label for="safepartrepair" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-shield-alt mr-1"></i>Part Repair for Backfill Scripts
                            </label>
                            <select id="safepartrepair" name="safepartrepair" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['safepartrepair'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to put unreceived parts into missed_parts table when running binaries(safe) or backfill scripts.</p>
                        </div>

                        <div>
                            <label for="maxpartrepair" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tools mr-1"></i>Maximum Repair Per Run
                            </label>
                            <input type="text" id="maxpartrepair" name="maxpartrepair" value="{{ $site['maxpartrepair'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of articles to attempt to repair at a time. If you notice that you are getting a lot of parts into the missed_parts table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads to safe scripts or stop using safe scripts until improves.</p>
                        </div>

                        <div>
                            <label for="partrepairmaxtries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-redo mr-1"></i>Maximum Repair Tries
                            </label>
                            <input type="text" id="partrepairmaxtries" name="partrepairmaxtries" value="{{ $site['partrepairmaxtries'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">Maximum amount of times to try part repair.</p>
                        </div>

                        <div>
                            <label for="processjpg" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-image mr-1"></i>Process JPG
                            </label>
                            <select id="processjpg" name="processjpg" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['processjpg'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to retrieve a JPG file while additional post processing, these are usually on XXX releases.</p>
                        </div>

                        <div>
                            <label for="processthumbnails" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-image mr-1"></i>Process Video Thumbnails
                            </label>
                            <select id="processthumbnails" name="processthumbnails" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['processthumbnails'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to process a video thumbnail image. You must have ffmpeg for this.</p>
                        </div>

                        <div>
                            <label for="processvideos" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-film mr-1"></i>Process Video Samples
                            </label>
                            <select id="processvideos" name="processvideos" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['processvideos'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to process a video sample, these videos are very short 1-3 seconds, 100KB on average, in ogg video format. You must have ffmpeg for this.</p>
                        </div>

                        <div>
                            <label for="segmentstodownload" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-download mr-1"></i>Number of Segments to Download
                            </label>
                            <input type="text" id="segmentstodownload" name="segmentstodownload" value="{{ $site['segmentstodownload'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of segments to download to generate the sample video file or jpg sample image. (Default 2)</p>
                        </div>

                        <div>
                            <label for="ffmpeg_duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-film mr-1"></i>Video Sample File Duration
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="ffmpeg_duration" name="ffmpeg_duration" value="{{ $site['ffmpeg_duration'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">seconds</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum duration (in seconds) for ffmpeg to generate the sample for. (Default 5)</p>
                        </div>

                        <div>
                            <label for="maxnestedlevels" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-layer-group mr-1"></i>Nested Archive Depth
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxnestedlevels" name="maxnestedlevels" value="{{ $site['maxnestedlevels'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">levels</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">If a rar/zip has rar/zip inside of it, how many times should we go in those inner rar/zip files.</p>
                        </div>

                        <div>
                            <label for="innerfileblacklist" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-ban mr-1"></i>Inner File Black List Regex
                            </label>
                            <textarea id="innerfileblacklist" name="innerfileblacklist" rows="3" placeholder="Example: /setup\.exe|password\.url/i"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ $site['innerfileblacklist'] ?? '' }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">You can add a regex here to set releases to potentially passworded when a file name inside a rar/zip matches this regex. <strong>You must ensure this regex is valid, a non valid regex will cause errors during processing!</strong></p>
                        </div>
                    </div>
                </div>

                <!-- Movie Trailer Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Movie Trailer Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="trailers_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-play-circle mr-1"></i>Fetch/Display Movie Trailers
                            </label>
                            <select id="trailers_display" name="trailers_display" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['trailers_display'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Fetch and display trailers from TraktTV (Requires API key) and/or TrailerAddict on the details page?</p>
                        </div>

                        <div>
                            <label for="trailers_size_x" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-arrows-alt-h mr-1"></i>Trailers Width
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="trailers_size_x" name="trailers_size_x" value="{{ $site['trailers_size_x'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">px</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Maximum width in pixels for the trailer window. (Default: 480)</p>
                        </div>

                        <div>
                            <label for="trailers_size_y" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-arrows-alt-v mr-1"></i>Trailers Height
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="trailers_size_y" name="trailers_size_y" value="{{ $site['trailers_size_y'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">px</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Maximum height in pixels for the trailer window. (Default: 345)</p>
                        </div>
                    </div>
                </div>

                <!-- Advanced - Postprocessing Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced - Postprocessing Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="timeoutseconds" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-clock mr-1"></i>Time in Seconds to Kill Unrar/7zip/Mediainfo/FFmpeg/Avconv
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="timeoutseconds" name="timeoutseconds" value="{{ $site['timeoutseconds'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">seconds</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">How much time to wait for unrar/7zip/mediainfo/ffmpeg/avconv before killing it, set to 0 to disable. 60 is a good value. Requires the GNU Timeout path to be set.</p>
                        </div>

                        <div>
                            <label for="maxaddprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-list-ol mr-1"></i>Maximum Add PP Per Run
                            </label>
                            <input type="text" id="maxaddprocessed" name="maxaddprocessed" value="{{ $site['maxaddprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of releases to process for passwords/previews/mediainfo per run. Every release gets processed here. This uses NNTP an connection, 1 per thread. This does not query Amazon.</p>
                        </div>

                        <div>
                            <label for="maxpartsprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-download mr-1"></i>Maximum Add PP Parts Downloaded
                            </label>
                            <input type="text" id="maxpartsprocessed" name="maxpartsprocessed" value="{{ $site['maxpartsprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">If a part fails to download while post processing, this will retry up to the amount you set, then give up.</p>
                        </div>

                        <div>
                            <label for="passchkattempts" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-check-double mr-1"></i>Maximum Add PP Parts Checked
                            </label>
                            <input type="text" id="passchkattempts" name="passchkattempts" value="{{ $site['passchkattempts'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">This overrides the above setting if set above 1. How many parts to check for a password before giving up. This slows down post processing massively, better to leave it 1.</p>
                        </div>

                        <div>
                            <label for="maxrageprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tv mr-1"></i>Maximum TV Per Run
                            </label>
                            <input type="text" id="maxrageprocessed" name="maxrageprocessed" value="{{ $site['maxrageprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of TV shows to processper run.</p>
                        </div>

                        <div>
                            <label for="maximdbprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-film mr-1"></i>Maximum Movies Per Run
                            </label>
                            <input type="text" id="maximdbprocessed" name="maximdbprocessed" value="{{ $site['maximdbprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of movies to process with IMDB per run.</p>
                        </div>

                        <div>
                            <label for="maxanidbprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-dragon mr-1"></i>Maximum AniDB Per Run
                            </label>
                            <input type="text" id="maxanidbprocessed" name="maxanidbprocessed" value="{{ $site['maxanidbprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of anime to process with anidb per run.</p>
                        </div>

                        <div>
                            <label for="maxmusicprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-music mr-1"></i>Maximum Music Per Run
                            </label>
                            <input type="text" id="maxmusicprocessed" name="maxmusicprocessed" value="{{ $site['maxmusicprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of music to process with amazon per run. This does not use an NNTP connection.</p>
                        </div>

                        <div>
                            <label for="maxgamesprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-gamepad mr-1"></i>Maximum Games Per Run
                            </label>
                            <input type="text" id="maxgamesprocessed" name="maxgamesprocessed" value="{{ $site['maxgamesprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of games to process with amazon per run. This does not use an NNTP connection.</p>
                        </div>

                        <div>
                            <label for="maxbooksprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-book mr-1"></i>Maximum Books Per Run
                            </label>
                            <input type="text" id="maxbooksprocessed" name="maxbooksprocessed" value="{{ $site['maxbooksprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of books to process with amazon per run. This does not use an NNTP connection</p>
                        </div>

                        <div>
                            <label for="maxxxxprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-video mr-1"></i>Maximum XXX Per Run
                            </label>
                            <input type="text" id="maxxxxprocessed" name="maxxxxprocessed" value="{{ $site['maxxxxprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of XXX to process per run.</p>
                        </div>

                        <div>
                            <label for="fixnamesperrun" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-edit mr-1"></i>fixReleaseNames Per Run
                            </label>
                            <input type="text" id="fixnamesperrun" name="fixnamesperrun" value="{{ $site['fixnamesperrun'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of releases to check per run (threaded script only).</p>
                        </div>

                        <div>
                            <label for="amazonsleep" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-hourglass mr-1"></i>Amazon Sleep Time
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="amazonsleep" name="amazonsleep" value="{{ $site['amazonsleep'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">ms</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">Sleep time in milliseconds to wait in between amazon requests. If you thread post-proc, multiply by the number of threads. ie Postprocessing Threads = 12, Amazon sleep time = 12000</p>
                        </div>
                    </div>
                </div>

                <!-- NFO Processing Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">NFO Processing Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="lookupnfo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-alt mr-1"></i>Lookup NFO
                            </label>
                            <select id="lookupnfo" name="lookupnfo" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['lookupnfo'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Whether to attempt to retrieve an nfo file from usenet.<br><strong>NOTE: disabling nfo lookups will disable movie lookups.</strong></p>
                        </div>

                        <div>
                            <label for="maxnfoprocessed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-file-text mr-1"></i>Maximum NFO Files Per Run
                            </label>
                            <input type="text" id="maxnfoprocessed" name="maxnfoprocessed" value="{{ $site['maxnfoprocessed'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum amount of NFO files to process per run. This uses NNTP an connection, 1 per thread. This does not query Amazon.</p>
                        </div>

                        <div>
                            <label for="maxsizetoprocessnfo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-upload mr-1"></i>Maximum Release Size to Process NFOs
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxsizetoprocessnfo" name="maxsizetoprocessnfo" value="{{ $site['maxsizetoprocessnfo'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">GB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The maximum size in gigabytes of a release to process it for NFOs. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="minsizetoprocessnfo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-download mr-1"></i>Minimum Release Size to Process NFOs
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="minsizetoprocessnfo" name="minsizetoprocessnfo" value="{{ $site['minsizetoprocessnfo'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">MB</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">The minimum size in megabytes of a release to process it for NFOs. If set to 0, then ignored.</p>
                        </div>

                        <div>
                            <label for="maxnforetries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-refresh mr-1"></i>Maximum Amount of Times to Redownload a NFO
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="maxnforetries" name="maxnforetries" value="{{ $site['maxnforetries'] ?? '' }}"
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <span class="px-3 py-2 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">times</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">How many times to retry when a NFO fails to download. If set to 0, we will not retry. The max is 7.</p>
                        </div>
                    </div>
                </div>

                <!-- Connection Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Connection Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="nntpretries" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-refresh mr-1"></i>NNTP Retry Attempts
                            </label>
                            <input type="text" id="nntpretries" name="nntpretries" value="{{ $site['nntpretries'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The maximum number of retry attempts to connect to nntp provider. On error, each retry takes approximately 5 seconds nntp returns reply. (Default 10)</p>
                        </div>

                        <div>
                            <label for="delaytime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-clock-o mr-1"></i>Delay Time Check
                            </label>
                            <input type="text" id="delaytime" name="delaytime" value="{{ $site['delaytime'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The time in hours to wait, since last activity, before releases without parts counts in the subject are are created.<br>Setting this below 2 hours could create incomplete releases.</p>
                        </div>

                        <div>
                            <label for="collection_timeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-hourglass-end mr-1"></i>Collection Timeout Check
                            </label>
                            <input type="text" id="collection_timeout" name="collection_timeout" value="{{ $site['collection_timeout'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">How many hours to wait before converting a collection into a release that is considered "stuck".<br>Default value is 48 hours.</p>
                        </div>
                    </div>
                </div>

                <!-- Developer Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Developer Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="showdroppedyencparts" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-bug mr-1"></i>Log Dropped Headers
                            </label>
                            <select id="showdroppedyencparts" name="showdroppedyencparts" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                @foreach($yesno['ids'] as $index => $yesnoId)
                                    <option value="{{ $yesnoId }}" {{ ($site['showdroppedyencparts'] ?? '') == $yesnoId ? 'selected' : '' }}>
                                        {{ $yesno['names'][$index] }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">For developers. Whether to log all headers that have 'yEnc' and are dropped. Logged to not_yenc/groupname.dropped.txt.</p>
                        </div>
                    </div>
                </div>

                <!-- Advanced - Threaded Settings -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced - Threaded Settings</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="binarythreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>Update Binaries Threads
                            </label>
                            <input type="text" id="binarythreads" name="binarythreads" value="{{ $site['binarythreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for update_binaries. If you notice that you are getting a lot of parts into the missed_parts table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads. At least until the cause can be determined.</p>
                        </div>

                        <div>
                            <label for="backfillthreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>Backfill Threads
                            </label>
                            <input type="text" id="backfillthreads" name="backfillthreads" value="{{ $site['backfillthreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for backfill.</p>
                        </div>

                        <div>
                            <label for="releasethreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>Update Releases Threads
                            </label>
                            <input type="text" id="releasethreads" name="releasethreads" value="{{ $site['releasethreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for releases update scripts.</p>
                        </div>

                        <div>
                            <label for="postthreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>Postprocessing Additional Threads
                            </label>
                            <input type="text" id="postthreads" name="postthreads" value="{{ $site['postthreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for additional postprocessing. This includes deep rar inspection, preview and sample creation and nfo processing.</p>
                        </div>

                        <div>
                            <label for="nfothreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>NFO Threads
                            </label>
                            <input type="text" id="nfothreads" name="nfothreads" value="{{ $site['nfothreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for nfo postprocessing. The max is 16, if you set anything higher it will use 16.</p>
                        </div>

                        <div>
                            <label for="postthreadsnon" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>Postprocessing Non-Amazon Threads
                            </label>
                            <input type="text" id="postthreadsnon" name="postthreadsnon" value="{{ $site['postthreadsnon'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for non-amazon postprocessing. This includes movies, anime and tv lookups.</p>
                        </div>

                        <div>
                            <label for="fixnamethreads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <i class="fa fa-tasks mr-1"></i>fixReleaseNames Threads
                            </label>
                            <input type="text" id="fixnamethreads" name="fixnamethreads" value="{{ $site['fixnamethreads'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-sm text-gray-500">The number of threads for fixReleasesNames. This includes md5, nfos, par2 and filenames.</p>
                        </div>
                    </div>
                </div>

                <!-- Note about full settings -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800 text-sm">
                        <i class="fa fa-info-circle mr-2"></i>
                        This is a simplified settings page. For complete site configuration, please use the full settings management interface or edit settings directly in the database.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-save mr-2"></i>Save Settings
                    </button>
                    <a href="{{ url('admin') }}" class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

