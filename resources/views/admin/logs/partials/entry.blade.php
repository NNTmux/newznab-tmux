{{-- One log entry row; rendered inside an Alpine x-for that exposes `entry`. --}}
<li class="group border-l-4 px-4 py-2.5 sm:px-6" :class="entryClass(entry)">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        <span class="rounded px-1.5 py-0.5 font-semibold uppercase tracking-wide" :class="levelBadgeClass(entry.level)" x-text="levelLabel(entry)"></span>
        <span x-show="entry.timestamp" class="font-mono text-gray-600 dark:text-gray-400" x-text="entry.timestamp"></span>
        <span x-show="entry.channel" class="text-gray-500 dark:text-gray-500" x-text="entry.channel"></span>
        <span x-show="entry.line" class="text-gray-400 dark:text-gray-500" x-text="lineLabel(entry)"></span>
        <span class="ml-auto flex items-center gap-1 opacity-70 transition group-hover:opacity-100">
            @if($showOpenInFile)
                <button type="button" class="rounded px-1.5 py-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" title="Open this entry in its file" @click="openInFile(entry)">
                    <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </button>
            @endif
            <button type="button" x-show="entry.truncated" class="rounded px-1.5 py-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" title="Load the full entry" @click="showFullEntry(entry)">
                <i class="fas fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
            </button>
            <button type="button" class="rounded px-1.5 py-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" title="Copy entry" @click="copyEntry(entry)">
                <i class="fas" :class="copyIconClass(entry)" aria-hidden="true"></i>
            </button>
        </span>
    </div>

    <div class="mt-1 cursor-pointer font-mono text-[13px] leading-relaxed text-gray-900 dark:text-gray-100" :class="messageClass(entry)" @click="toggleEntry(entry)">
        <template x-if="hasMessageSegments(entry)">
            <span><template x-for="(segment, index) in entry.message_segments" :key="index"><span :class="segmentClass(segment)" x-text="segment.text"></span></template></span>
        </template>
        <template x-if="hasMessageSegments(entry) === false">
            <span x-text="entry.message"></span>
        </template>
    </div>

    <template x-if="entry._hasBody">
        <div class="mt-1">
            <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400" @click="toggleEntry(entry)">
                <i class="fas text-[10px]" :class="isExpanded(entry) ? 'fa-chevron-down' : 'fa-chevron-right'" aria-hidden="true"></i>
                <span x-text="isExpanded(entry) ? 'Hide details' : 'Show details'"></span>
            </button>
            <pre x-show="isExpanded(entry)" class="mt-1.5 max-h-[60vh] overflow-auto rounded-lg bg-gray-950 p-3 font-mono text-xs leading-relaxed text-gray-200"><template x-if="hasBodySegments(entry)"><span><template x-for="(segment, index) in entry.body_segments" :key="index"><span :class="segmentClass(segment)" x-text="segment.text"></span></template></span></template><template x-if="hasBodySegments(entry) === false"><span x-text="entry.body"></span></template></pre>
        </div>
    </template>

    <p x-show="entry.truncated" class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">Entry truncated for display. Use the expand button to load it in full.</p>
</li>
