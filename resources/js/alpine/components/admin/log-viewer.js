/**
 * Alpine.data('adminLogViewer') - Admin log browser with cross-file search.
 *
 * Browsing a file pages backwards through entries via a byte cursor (`before`).
 * Searching fans out over batches of files (big files alone, small files packed together),
 * three requests at a time; a newer search aborts every in-flight request of the previous one.
 * When the Manticore log index is available the server answers caught-up files from it (and the
 * rest with grep); level / channel counts then come from the index via the facets endpoint.
 */
import Alpine from '@alpinejs/csp';

const SEARCH_DEBOUNCE_MS = 300;
const SEARCH_CONCURRENCY = 3;
const BATCH_BYTES = 16 * 1024 * 1024;
const AUTO_OPEN_GROUPS = 3;
const MIN_QUERY_LENGTH = 2;
const MAX_FACET_FILES = 200;

const LEVEL_BADGES = {
    debug: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200',
    notice: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-200',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
    error: 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200',
    critical: 'bg-red-600 text-white',
    alert: 'bg-fuchsia-600 text-white',
    emergency: 'bg-black text-white dark:bg-white dark:text-black',
};

const LEVEL_BORDERS = {
    debug: 'border-l-gray-300 dark:border-l-gray-600',
    info: 'border-l-blue-400',
    notice: 'border-l-cyan-400',
    warning: 'border-l-amber-400',
    error: 'border-l-red-500',
    critical: 'border-l-red-700',
    alert: 'border-l-fuchsia-600',
    emergency: 'border-l-black dark:border-l-white',
};

Alpine.data('adminLogViewer', () => ({
    // Files
    files: [],
    fileFilter: '',
    selectedPath: '',
    excluded: {},
    engine: 'php',
    levelOptions: [],
    limitOptions: [],
    maxFilesPerSearch: 25,

    // Filters
    query: '',
    regex: false,
    caseSensitive: false,
    scope: 'file',
    levels: [],
    channels: [],
    from: '',
    to: '',
    limit: 100,

    // Index facets ({ levels, channels, partial } or null when unavailable)
    indexEnabled: false,
    facets: null,

    // Browse mode
    entries: [],
    before: null,
    startBefore: null,
    anchorOffset: null,
    loading: false,
    loadingMore: false,
    budgetExhausted: false,
    error: '',

    // Search mode
    mode: 'browse',
    groups: [],
    searching: false,
    searchError: '',
    progress: { done: 0, total: 0, matches: 0, elapsedMs: 0 },

    // Entry UI state
    expanded: {},
    copiedKey: '',

    _urls: {},
    _generation: 0,
    _browseGeneration: 0,
    _searchAbort: null,
    _browseAbort: null,
    _facetsAbort: null,
    _facetsGeneration: 0,
    _debounce: null,
    _groupIndex: {},

    init() {
        const data = this.$el.dataset;

        this._urls = {
            files: data.filesUrl,
            entries: data.entriesUrl,
            entry: data.entryUrl,
            search: data.searchUrl,
            facets: data.facetsUrl,
            download: data.downloadUrl,
            truncate: data.truncateUrl,
            destroy: data.destroyUrl,
        };
        this.files = this._parseJson(data.files, []);
        this.levelOptions = this._parseJson(data.levels, []);
        this.limitOptions = this._parseJson(data.limitOptions, [100]);
        this.limit = Number.parseInt(data.defaultLimit ?? '100', 10) || 100;
        this.maxFilesPerSearch = Number.parseInt(data.maxFilesPerSearch ?? '25', 10) || 25;
        this.engine = data.engine || 'php';
        this.indexEnabled = data.indexEnabled === '1';

        const params = new URLSearchParams(window.location.search);
        this.selectedPath = params.get('file') || data.initialFile || (this.files[0]?.path ?? '');
        this.query = params.get('q') ?? params.get('search') ?? data.initialQuery ?? '';
        this.regex = params.get('regex') === '1';
        this.caseSensitive = params.get('case') === '1';
        this.scope = params.get('scope') === 'all' ? 'all' : 'file';
        this.levels = params.getAll('levels[]').filter(level => this.levelOptions.includes(level));
        this.channels = params.getAll('channels[]').filter(channel => channel !== '');
        this.from = params.get('from') ?? '';
        this.to = params.get('to') ?? '';

        this.refresh();
    },

    // ----------------------------------------------------------------- files

    filteredFiles() {
        const needle = this.fileFilter.trim().toLowerCase();
        if (needle === '') {
            return this.files;
        }
        return this.files.filter(file => file.path.toLowerCase().includes(needle));
    },

    selectedFile() {
        return this.files.find(file => file.path === this.selectedPath) ?? null;
    },

    hasSelectedFile() {
        return this.selectedFile() !== null;
    },

    hasFiles() {
        return this.files.length > 0;
    },

    isSelected(path) {
        return path === this.selectedPath;
    },

    fileRowClass(path) {
        return this.isSelected(path)
            ? 'bg-blue-50 text-blue-900 ring-1 ring-inset ring-blue-200 dark:bg-blue-900/40 dark:text-blue-100 dark:ring-blue-800'
            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800';
    },

    selectFile(path) {
        this.selectedPath = path;
        this.startBefore = null;
        this.anchorOffset = null;
        if (this.scope === 'file') {
            this.loadFacets();
        }
        if (this.scope === 'file' && this.mode === 'search') {
            this.runSearch();
        } else if (this.mode !== 'search') {
            this.loadEntries();
        }
        this.syncUrl();
    },

    isIncluded(path) {
        return !this.excluded[path];
    },

    toggleIncluded(path) {
        this.excluded = { ...this.excluded, [path]: !this.excluded[path] };
        this._rerunIfSearching();
    },

    includeAll() {
        this.excluded = {};
        this._rerunIfSearching();
    },

    includeNone() {
        const excluded = {};
        this.filteredFiles().forEach(file => { excluded[file.path] = true; });
        this.excluded = { ...this.excluded, ...excluded };
        this._rerunIfSearching();
    },

    includedCount() {
        return this.files.filter(file => !this.excluded[file.path]).length;
    },

    includedLabel() {
        return `Searching ${this.includedCount()} of ${this.files.length} files`;
    },

    includeTitle(file) {
        return `Include ${file.path} in search`;
    },

    fileDirectory(file) {
        return file.directory ?? 'logs';
    },

    fileMeta(file) {
        return `${file.human_size} · ${this.relativeTime(file.modified_at)}`;
    },

    hasIndexState(file) {
        return Boolean(file.index);
    },

    indexStateClass(file) {
        const state = file.index?.state;
        if (state === 'indexed') return 'text-emerald-500';
        if (state === 'partial') return 'text-amber-500';
        return 'text-gray-300 dark:text-gray-600';
    },

    indexStateTitle(file) {
        const index = file.index;
        if (!index) {
            return '';
        }
        if (index.state === 'indexed') {
            return `Indexed ${this.relativeTime(index.indexed_at)}; searches use the log index`;
        }
        if (index.state === 'partial') {
            const percent = file.size > 0 ? Math.floor((index.indexed_bytes / file.size) * 100) : 0;
            return `Indexing (${percent}%); searches scan this file with grep until it catches up`;
        }
        return 'Not indexed yet; searches scan this file with grep';
    },

    isAllScope() {
        return this.scope === 'all';
    },

    async refreshFiles() {
        try {
            const payload = await this._fetchJson(this._urls.files, new URLSearchParams());
            this.files = payload.files ?? [];
            this.engine = payload.engine ?? this.engine;
            if (!this.hasSelectedFile()) {
                this.selectedPath = this.files[0]?.path ?? '';
            }
        } catch (error) {
            this._toast(error.message, 'error');
        }
    },

    // ----------------------------------------------------------------- filters

    onQueryInput() {
        window.clearTimeout(this._debounce);
        this._debounce = window.setTimeout(() => this.refresh(), SEARCH_DEBOUNCE_MS);
    },

    onSearchKeydown(event) {
        if (event.key === 'Enter') {
            window.clearTimeout(this._debounce);
            this.refresh();
        } else if (event.key === 'Escape') {
            this.clearSearch();
            event.target.blur();
        }
    },

    clearSearch() {
        window.clearTimeout(this._debounce);
        this.query = '';
        this.refresh();
    },

    hasQuery() {
        return this.query.trim() !== '';
    },

    toggleRegex() {
        this.regex = !this.regex;
        this._rerunIfQuery();
    },

    toggleCase() {
        this.caseSensitive = !this.caseSensitive;
        this._rerunIfQuery();
    },

    setScope(scope) {
        if (this.scope === scope) {
            return;
        }
        this.scope = scope;
        this.refresh();
    },

    scopeButtonClass(scope) {
        return this.scope === scope
            ? 'bg-blue-600 text-white shadow-sm'
            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';
    },

    toggleButtonClass(active) {
        return active
            ? 'border-blue-500 bg-blue-600 text-white'
            : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
    },

    toggleLevel(level) {
        this.levels = this.levels.includes(level)
            ? this.levels.filter(item => item !== level)
            : [...this.levels, level];
        this.refresh();
    },

    clearLevels() {
        this.levels = [];
        this.refresh();
    },

    isLevelActive(level) {
        return this.levels.includes(level);
    },

    hasLevels() {
        return this.levels.length > 0;
    },

    levelChipClass(level) {
        return this.levels.includes(level)
            ? `${LEVEL_BADGES[level] ?? ''} ring-2 ring-offset-1 ring-blue-500 dark:ring-offset-gray-900`
            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700';
    },

    levelCount(level) {
        if (this.facets) {
            return this.facets.levels[level] ?? 0;
        }
        const entries = this.mode === 'search'
            ? this.groups.flatMap(group => group.entries)
            : this.entries;
        return entries.filter(entry => entry.level === level).length;
    },

    levelCountLabel(level) {
        return this.levelCount(level).toLocaleString();
    },

    countsTitle() {
        if (!this.facets) {
            return 'Counts of the entries loaded on this page';
        }
        return this.facets.partial
            ? 'Counts from the log index; some files are still being indexed'
            : 'Counts from the log index across the selected files';
    },

    // ----------------------------------------------------------------- channels

    channelOptions() {
        const counted = Object.keys(this.facets?.channels ?? {});
        const selected = this.channels.filter(channel => !counted.includes(channel));
        return [...counted, ...selected];
    },

    hasChannelOptions() {
        return this.channelOptions().length > 0;
    },

    toggleChannel(channel) {
        this.channels = this.channels.includes(channel)
            ? this.channels.filter(item => item !== channel)
            : [...this.channels, channel];
        this.refresh();
    },

    clearChannels() {
        this.channels = [];
        this.refresh();
    },

    isChannelActive(channel) {
        return this.channels.includes(channel);
    },

    hasChannels() {
        return this.channels.length > 0;
    },

    channelChipClass(channel) {
        return this.channels.includes(channel)
            ? 'bg-indigo-100 text-indigo-800 ring-2 ring-offset-1 ring-blue-500 dark:bg-indigo-900/60 dark:text-indigo-200 dark:ring-offset-gray-900'
            : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700';
    },

    channelCount(channel) {
        return this.facets?.channels?.[channel] ?? 0;
    },

    channelCountLabel(channel) {
        return this.channelCount(channel).toLocaleString();
    },

    // ----------------------------------------------------------------- time range

    hasTimeRange() {
        return this.from !== '' || this.to !== '';
    },

    onRangeChange() {
        this.refresh();
    },

    clearRange() {
        this.from = '';
        this.to = '';
        this.refresh();
    },

    hasFilterOnlySearch() {
        return this.channels.length > 0 || this.hasTimeRange() || (this.scope === 'all' && this.levels.length > 0);
    },

    onLimitChange() {
        if (this.mode === 'browse') {
            this.loadEntries();
        }
    },

    /**
     * Decide between browsing the selected file and searching, then load.
     */
    refresh() {
        this.syncUrl();
        this.loadFacets();
        const term = this.query.trim();
        const filterOnly = term === '' && this.hasFilterOnlySearch();

        if (term.length >= MIN_QUERY_LENGTH || filterOnly) {
            this.runSearch();
            return;
        }

        this._cancelSearch();
        this.mode = 'browse';
        this.searchError = term.length > 0 ? `Type at least ${MIN_QUERY_LENGTH} characters to search.` : '';
        this.loadEntries();
    },

    // ----------------------------------------------------------------- facets

    async loadFacets() {
        this._facetsAbort?.abort();
        const generation = ++this._facetsGeneration;
        const targets = this._targets();

        if (!this.indexEnabled || !this._urls.facets || targets.length === 0 || targets.length > MAX_FACET_FILES) {
            this.facets = null;
            return;
        }

        this._facetsAbort = new AbortController();
        const params = this._searchParams(targets.map(file => file.path));
        if (this.query.trim().length < MIN_QUERY_LENGTH) {
            params.delete('q');
        }

        try {
            const payload = await this._fetchJson(this._urls.facets, params, this._facetsAbort.signal);
            if (generation === this._facetsGeneration) {
                this.facets = payload.available
                    ? { levels: payload.levels ?? {}, channels: payload.channels ?? {}, partial: Boolean(payload.partial) }
                    : null;
            }
        } catch (error) {
            if (error.name !== 'AbortError' && generation === this._facetsGeneration) {
                this.facets = null;
            }
        }
    },

    // ----------------------------------------------------------------- browse

    async loadEntries(append = false) {
        const file = this.selectedFile();
        if (!file) {
            this.entries = [];
            return;
        }

        this._browseAbort?.abort();
        this._browseAbort = new AbortController();
        const generation = ++this._browseGeneration;

        const params = new URLSearchParams({ file: file.path, limit: String(this.limit) });
        const before = append ? this.before : this.startBefore;
        if (before !== null && before !== undefined) {
            params.set('before', String(before));
        }
        this.levels.forEach(level => params.append('levels[]', level));

        if (append) {
            this.loadingMore = true;
        } else {
            this.loading = true;
            this.error = '';
        }

        try {
            const payload = await this._fetchJson(this._urls.entries, params, this._browseAbort.signal);
            if (generation !== this._browseGeneration) {
                return;
            }
            const entries = (payload.entries ?? []).map(entry => this._decorate(entry, file.path));
            this.entries = append ? [...this.entries, ...entries] : entries;
            this.before = payload.before ?? null;
            this.budgetExhausted = Boolean(payload.budget_exhausted);
            this._replaceFile(payload.file);
        } catch (error) {
            if (error.name !== 'AbortError' && generation === this._browseGeneration) {
                this.error = error.message;
            }
        } finally {
            if (generation === this._browseGeneration) {
                this.loading = false;
                this.loadingMore = false;
            }
        }
    },

    loadOlder() {
        if (this.before !== null && !this.loadingMore) {
            this.loadEntries(true);
        }
    },

    hasOlder() {
        return this.before !== null;
    },

    isBrowsingFromAnchor() {
        return this.startBefore !== null;
    },

    jumpToLatest() {
        this.startBefore = null;
        this.anchorOffset = null;
        this.loadEntries();
    },

    isBrowseMode() {
        return this.mode === 'browse';
    },

    isSearchMode() {
        return this.mode === 'search';
    },

    hasEntries() {
        return this.entries.length > 0;
    },

    showBrowseEmpty() {
        return this.isBrowseMode() && !this.loading && !this.error && !this.hasEntries() && this.hasSelectedFile();
    },

    // ----------------------------------------------------------------- search

    runSearch() {
        this._cancelSearch();
        const generation = ++this._generation;
        const controller = new AbortController();
        this._searchAbort = controller;

        const targets = this._targets();

        this.mode = 'search';
        this.searchError = '';
        this.expanded = {};
        this._groupIndex = {};
        this.groups = targets.map((file, index) => {
            this._groupIndex[file.path] = index;
            return {
                path: file.path,
                name: file.name,
                humanSize: file.human_size,
                status: 'pending',
                engine: '',
                estimate: false,
                total: 0,
                hasMore: false,
                before: null,
                timedOut: false,
                error: '',
                entries: [],
                open: false,
                loadingMore: false,
            };
        });
        this.progress = { done: 0, total: targets.length, matches: 0, elapsedMs: 0 };

        if (targets.length === 0) {
            this.searchError = 'No log files selected to search.';
            return;
        }

        this.searching = true;
        const startedAt = performance.now();
        const batches = this._buildBatches(targets);
        let next = 0;

        const worker = async () => {
            while (next < batches.length && generation === this._generation) {
                const batch = batches[next++];
                await this._searchBatch(batch, generation, controller.signal);
                if (generation === this._generation) {
                    this.progress.elapsedMs = Math.round(performance.now() - startedAt);
                }
            }
        };

        Promise.all(Array.from({ length: Math.min(SEARCH_CONCURRENCY, batches.length) }, worker))
            .finally(() => {
                if (generation === this._generation) {
                    this.searching = false;
                }
            });
    },

    async _searchBatch(batch, generation, signal) {
        const params = this._searchParams(batch.map(file => file.path));

        try {
            const payload = await this._fetchJson(this._urls.search, params, signal);
            if (generation !== this._generation) {
                return;
            }
            (payload.results ?? []).forEach(result => this._applyResult(result, false));
            if (payload.engine) {
                this.engine = payload.engine;
            }
        } catch (error) {
            if (error.name === 'AbortError' || generation !== this._generation) {
                return;
            }
            if (error.status === 422) {
                // Validation errors (e.g. invalid regex) apply to every batch: stop the whole search.
                this.searchError = error.message;
                this._cancelSearch();
                return;
            }
            batch.forEach(file => {
                const group = this._group(file.path);
                if (group) {
                    group.status = 'error';
                    group.error = error.message;
                }
            });
        } finally {
            if (generation === this._generation) {
                this.progress.done += batch.length;
            }
        }
    },

    _applyResult(result, append) {
        const group = this._group(result.file);
        if (!group) {
            return;
        }
        const entries = (result.entries ?? []).map(entry => this._decorate(entry, group.path));
        const hadEntries = group.entries.length > 0;

        group.entries = append ? [...group.entries, ...entries] : entries;
        group.status = result.error ? 'error' : 'done';
        group.error = result.error ?? '';
        group.timedOut = Boolean(result.timed_out);
        group.hasMore = Boolean(result.has_more);
        group.before = result.before ?? null;
        group.engine = result.engine ?? group.engine;

        if (!append) {
            group.total = result.total_matches ?? 0;
            group.estimate = Boolean(result.total_is_estimate);
            this.progress.matches += group.total;
            const openGroups = this.groups.filter(item => item.open).length;
            if (!hadEntries && entries.length > 0 && (openGroups < AUTO_OPEN_GROUPS || this.groups.length === 1)) {
                group.open = true;
            }
        }
    },

    async loadMoreForGroup(path) {
        const group = this._group(path);
        if (!group || group.before === null || group.loadingMore) {
            return;
        }
        const generation = this._generation;
        const params = this._searchParams([path]);
        params.set('before', String(group.before));
        group.loadingMore = true;

        try {
            const payload = await this._fetchJson(this._urls.search, params, this._searchAbort?.signal);
            if (generation === this._generation) {
                (payload.results ?? []).forEach(result => this._applyResult(result, true));
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this._toast(error.message, 'error');
            }
        } finally {
            group.loadingMore = false;
        }
    },

    toggleGroup(path) {
        const group = this._group(path);
        if (group) {
            group.open = !group.open;
        }
    },

    expandAllGroups() {
        this.groups.forEach(group => { if (group.entries.length > 0) group.open = true; });
    },

    collapseAllGroups() {
        this.groups.forEach(group => { group.open = false; });
    },

    visibleGroups() {
        return this.groups.filter(group => group.total > 0 || group.status === 'error' || group.timedOut);
    },

    showSearchEmpty() {
        return this.isSearchMode() && !this.searching && !this.searchError && this.visibleGroups().length === 0;
    },

    groupChevronClass(group) {
        return group.open ? 'fa-chevron-down' : 'fa-chevron-right';
    },

    groupCountLabel(group) {
        const count = `${group.estimate ? '≈ ' : ''}${group.total.toLocaleString()}`;
        if (group.engine === 'manticore') {
            return `${count} matching entr${group.total === 1 ? 'y' : 'ies'}`;
        }
        return `${count} matching line${group.total === 1 ? '' : 's'}`;
    },

    hasEngine(group) {
        return group.engine !== '';
    },

    engineLabel(group) {
        return group.engine === 'manticore' ? 'index' : group.engine;
    },

    engineTitle(group) {
        return group.engine === 'manticore'
            ? 'Answered from the Manticore log index'
            : `Scanned with ${group.engine} (regex search, file not indexed yet, or index unavailable)`;
    },

    engineBadgeClass(group) {
        return group.engine === 'manticore'
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200'
            : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
    },

    groupShownLabel(group) {
        return `showing ${group.entries.length.toLocaleString()} entr${group.entries.length === 1 ? 'y' : 'ies'}`;
    },

    filesWithMatches() {
        return this.groups.filter(group => group.total > 0).length;
    },

    progressPercent() {
        return this.progress.total === 0 ? 0 : Math.round((this.progress.done / this.progress.total) * 100);
    },

    progressStyle() {
        return `width: ${this.progressPercent()}%`;
    },

    progressLabel() {
        const parts = [
            `Searched ${this.progress.done}/${this.progress.total} file${this.progress.total === 1 ? '' : 's'}`,
            `${this.progress.matches.toLocaleString()} matching line${this.progress.matches === 1 ? '' : 's'} in ${this.filesWithMatches()} file${this.filesWithMatches() === 1 ? '' : 's'}`,
        ];
        if (this.progress.elapsedMs > 0) {
            parts.push(`${(this.progress.elapsedMs / 1000).toFixed(2)}s`);
        }
        parts.push(`engine: ${this.engine}`);
        return parts.join(' · ');
    },

    _buildBatches(files) {
        const batches = [];
        let current = [];
        let currentBytes = 0;
        const flush = () => {
            if (current.length > 0) {
                batches.push(current);
            }
            current = [];
            currentBytes = 0;
        };

        files.forEach(file => {
            if (file.size >= BATCH_BYTES) {
                batches.push([file]);
                return;
            }
            if (currentBytes + file.size > BATCH_BYTES || current.length >= this.maxFilesPerSearch) {
                flush();
            }
            current.push(file);
            currentBytes += file.size;
        });
        flush();

        return batches;
    },

    _searchParams(paths) {
        const params = new URLSearchParams();
        const term = this.query.trim();
        if (term !== '') {
            params.set('q', term);
        }
        params.set('regex', this.regex ? '1' : '0');
        params.set('case', this.caseSensitive ? '1' : '0');
        this.levels.forEach(level => params.append('levels[]', level));
        this.channels.forEach(channel => params.append('channels[]', channel));
        if (this.from !== '') {
            params.set('from', this.from);
        }
        if (this.to !== '') {
            params.set('to', this.to);
        }
        paths.forEach(path => params.append('files[]', path));
        return params;
    },

    _targets() {
        return this.scope === 'all'
            ? this.files.filter(file => !this.excluded[file.path])
            : this.files.filter(file => file.path === this.selectedPath);
    },

    _cancelSearch() {
        this._generation++;
        this._searchAbort?.abort();
        this._searchAbort = null;
        this.searching = false;
    },

    _rerunIfQuery() {
        if (this.hasQuery()) {
            this.refresh();
        } else {
            this.syncUrl();
        }
    },

    _rerunIfSearching() {
        if (this.scope === 'all') {
            this.loadFacets();
        }
        if (this.mode === 'search' && this.scope === 'all') {
            this.runSearch();
        }
    },

    _group(path) {
        const index = this._groupIndex[path];
        return index === undefined ? null : this.groups[index];
    },

    // ----------------------------------------------------------------- entries

    _decorate(entry, path) {
        return {
            ...entry,
            _key: `${path}:${entry.offset}`,
            _path: path,
            _hasBody: typeof entry.body === 'string' && entry.body !== '',
        };
    },

    isExpanded(entry) {
        return Boolean(this.expanded[entry._key]);
    },

    toggleEntry(entry) {
        this.expanded = { ...this.expanded, [entry._key]: !this.expanded[entry._key] };
    },

    hasMessageSegments(entry) {
        return Array.isArray(entry.message_segments);
    },

    hasBodySegments(entry) {
        return Array.isArray(entry.body_segments);
    },

    segmentClass(segment) {
        return segment.hit ? 'rounded-sm bg-yellow-200 px-0.5 text-gray-900 dark:bg-yellow-500/70 dark:text-gray-950' : '';
    },

    entryClass(entry) {
        const border = LEVEL_BORDERS[entry.level] ?? 'border-l-gray-200 dark:border-l-gray-700';
        const anchor = this.anchorOffset !== null && entry.offset === this.anchorOffset && entry._path === this.selectedPath
            ? ' bg-yellow-50 dark:bg-yellow-900/20'
            : '';
        return `${border}${anchor}`;
    },

    messageClass(entry) {
        return this.isExpanded(entry) ? 'whitespace-pre-wrap break-words' : 'line-clamp-3 break-words';
    },

    levelBadgeClass(level) {
        return LEVEL_BADGES[level] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
    },

    levelLabel(entry) {
        return entry.level ? entry.level.toUpperCase() : 'LINE';
    },

    lineLabel(entry) {
        return entry.line ? `line ${entry.line.toLocaleString()}` : '';
    },

    async showFullEntry(entry) {
        const params = new URLSearchParams({ file: entry._path, offset: String(entry.offset) });
        try {
            const payload = await this._fetchJson(this._urls.entry, params);
            const full = payload.entry ?? {};
            entry.message = full.message ?? entry.message;
            entry.body = full.body ?? entry.body;
            entry.truncated = Boolean(full.truncated);
            entry.message_segments = null;
            entry.body_segments = null;
            entry._hasBody = entry.body !== '';
            this.expanded = { ...this.expanded, [entry._key]: true };
        } catch (error) {
            this._toast(error.message, 'error');
        }
    },

    openInFile(entry) {
        window.clearTimeout(this._debounce);
        this._cancelSearch();
        this.query = '';
        this.mode = 'browse';
        this.searchError = '';
        this.selectedPath = entry._path;
        this.startBefore = entry.end;
        this.anchorOffset = entry.offset;
        this.levels = [];
        this.channels = [];
        this.from = '';
        this.to = '';
        this.syncUrl();
        this.loadFacets();
        this.loadEntries();
    },

    async copyEntry(entry) {
        const header = entry.level
            ? `[${entry.timestamp}] ${entry.channel}.${entry.level.toUpperCase()}: ${entry.message}`
            : entry.message;
        const text = entry.body ? `${header}\n${entry.body}` : header;

        try {
            await navigator.clipboard.writeText(text);
            this.copiedKey = entry._key;
            window.setTimeout(() => { if (this.copiedKey === entry._key) this.copiedKey = ''; }, 1500);
        } catch {
            this._toast('Copy failed: the clipboard is unavailable in this context.', 'error');
        }
    },

    copyIconClass(entry) {
        return this.copiedKey === entry._key ? 'fa-check text-green-500' : 'fa-copy';
    },

    // ----------------------------------------------------------------- file actions

    downloadHref() {
        return this.selectedPath ? `${this._urls.download}?${new URLSearchParams({ file: this.selectedPath })}` : '#';
    },

    canDelete() {
        const file = this.selectedFile();
        return file !== null && !file.active;
    },

    deleteTitle() {
        return this.canDelete() ? 'Delete this log file' : 'Recently written logs cannot be deleted; truncate instead';
    },

    async truncateFile() {
        const file = this.selectedFile();
        if (!file) {
            return;
        }
        const confirmed = await this._confirm({
            title: 'Truncate log file?',
            message: `Empty ${file.path} (${file.human_size})?`,
            details: 'All content is removed permanently. Processes writing to it keep appending to the now-empty file.',
            type: 'danger',
            confirmText: 'Truncate',
            cancelText: 'Cancel',
        });
        if (!confirmed) {
            return;
        }

        try {
            await this._send(this._urls.truncate, 'POST', { file: file.path });
            this._toast(`${file.path} truncated.`, 'success');
            await this.refreshFiles();
            this.refresh();
        } catch (error) {
            this._toast(error.message, 'error');
        }
    },

    async deleteFile() {
        const file = this.selectedFile();
        if (!file || file.active) {
            return;
        }
        const confirmed = await this._confirm({
            title: 'Delete log file?',
            message: `Permanently delete ${file.path} (${file.human_size})?`,
            details: 'This cannot be undone.',
            type: 'danger',
            confirmText: 'Delete',
            cancelText: 'Cancel',
        });
        if (!confirmed) {
            return;
        }

        try {
            await this._send(this._urls.destroy, 'DELETE', { file: file.path });
            this._toast(`${file.path} deleted.`, 'success');
            await this.refreshFiles();
            this.startBefore = null;
            this.anchorOffset = null;
            this.refresh();
        } catch (error) {
            this._toast(error.message, 'error');
        }
    },

    // ----------------------------------------------------------------- misc

    onGlobalKeydown(event) {
        const tag = (event.target?.tagName ?? '').toLowerCase();
        const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable;
        if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey) {
            event.preventDefault();
            this.$refs.search?.focus();
            this.$refs.search?.select();
        }
    },

    syncUrl() {
        const params = new URLSearchParams();
        if (this.selectedPath) params.set('file', this.selectedPath);
        if (this.query.trim() !== '') params.set('q', this.query.trim());
        if (this.regex) params.set('regex', '1');
        if (this.caseSensitive) params.set('case', '1');
        if (this.scope === 'all') params.set('scope', 'all');
        this.levels.forEach(level => params.append('levels[]', level));
        this.channels.forEach(channel => params.append('channels[]', channel));
        if (this.from !== '') params.set('from', this.from);
        if (this.to !== '') params.set('to', this.to);
        const search = params.toString();
        window.history.replaceState(null, '', `${window.location.pathname}${search ? `?${search}` : ''}`);
    },

    relativeTime(iso) {
        const timestamp = Date.parse(iso);
        if (Number.isNaN(timestamp)) {
            return '';
        }
        const seconds = Math.round((Date.now() - timestamp) / 1000);
        if (seconds < 60) return 'just now';
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) return `${minutes} min ago`;
        const hours = Math.round(minutes / 60);
        if (hours < 48) return `${hours} h ago`;
        return `${Math.round(hours / 24)} days ago`;
    },

    absoluteTime(iso) {
        const timestamp = Date.parse(iso);
        return Number.isNaN(timestamp) ? '' : new Date(timestamp).toLocaleString();
    },

    selectedMeta() {
        const file = this.selectedFile();
        if (!file) {
            return '';
        }
        return `${file.human_size} · modified ${this.relativeTime(file.modified_at)}${file.structured ? '' : ' · plain text'}`;
    },

    _replaceFile(file) {
        if (!file) {
            return;
        }
        const index = this.files.findIndex(item => item.path === file.path);
        if (index !== -1) {
            this.files.splice(index, 1, file);
        }
    },

    async _fetchJson(url, params, signal = undefined) {
        const query = params.toString();
        const response = await fetch(query ? `${url}?${query}` : url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            signal,
        });
        return this._readResponse(response);
    },

    async _send(url, method, body) {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const response = await fetch(url, {
            method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        return this._readResponse(response);
    },

    async _readResponse(response) {
        let payload = null;
        try {
            payload = await response.json();
        } catch {
            payload = null;
        }
        if (!response.ok) {
            const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
            const error = new Error(firstError || payload?.message || `Request failed (${response.status}).`);
            error.status = response.status;
            throw error;
        }
        return payload ?? {};
    },

    async _confirm(options) {
        if (typeof window.showConfirm === 'function') {
            return window.showConfirm(options);
        }
        return window.confirm(`${options.title}\n\n${options.message}`);
    },

    _toast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    },

    _parseJson(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch {
            return fallback;
        }
    },
}));
