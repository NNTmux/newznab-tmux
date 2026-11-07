            {{-- Video/Audio Metadata: compact summary lines, only rendered when a field has a real value.
                 The subtitle lookup is an aggregate query, so it returns a row (subs = null) even when empty. --}}
            @php
                $mediaValue = static function ($row, string $field): string {
                    $value = trim((string) data_get($row, $field, ''));

                    return $value === '0' ? '' : $value;
                };

                $videoParts = [];
                if (! empty($reVideo)) {
                    $durationMinutes = (int) round(((int) $mediaValue($reVideo, 'videoduration')) / 60000);
                    $width = $mediaValue($reVideo, 'videowidth');
                    $height = $mediaValue($reVideo, 'videoheight');
                    $videoParts = array_values(array_filter([
                        'Container' => $mediaValue($reVideo, 'containerformat'),
                        'Format' => $mediaValue($reVideo, 'videoformat'),
                        'Codec' => $mediaValue($reVideo, 'videocodec'),
                        'Resolution' => $width !== '' && $height !== '' ? $width.'×'.$height : '',
                        'Aspect' => $mediaValue($reVideo, 'videoaspect'),
                        'Frame rate' => $mediaValue($reVideo, 'videoframerate') !== '' ? $mediaValue($reVideo, 'videoframerate').' fps' : '',
                        'Duration' => $durationMinutes > 0 ? $durationMinutes.' min' : '',
                        'Bit rate' => $mediaValue($reVideo, 'overallbitrate'),
                        'Encoder' => $mediaValue($reVideo, 'videolibrary'),
                    ], static fn (string $value): bool => $value !== ''));
                }

                $audioTracks = [];
                foreach ((array) ($reAudio ?: []) as $track) {
                    $rate = $mediaValue($track, 'audiosamplerate');
                    $channels = $mediaValue($track, 'audiochannels');
                    $parts = array_values(array_filter([
                        $mediaValue($track, 'audiolanguage'),
                        $mediaValue($track, 'audioformat'),
                        $mediaValue($track, 'audiocodec'),
                        $channels !== '' ? $channels.' ch' : '',
                        $mediaValue($track, 'audiobitrate'),
                        // Some sources already include the unit ("48.0 kHz").
                        $rate !== '' ? (preg_match('/[a-z]/i', $rate) ? $rate : $rate.' Hz') : '',
                    ], static fn (string $value): bool => $value !== ''));
                    if ($parts !== []) {
                        $audioTracks[] = $parts;
                    }
                }

                $subtitleLanguages = collect(explode(',', (string) data_get($reSubs ?? null, 'subs', '')))
                    ->map(static fn (string $language): string => trim($language))
                    ->filter()
                    ->unique()
                    ->values();
                $visibleSubtitles = 12;
            @endphp
            @if($videoParts !== [] || $audioTracks !== [] || $subtitleLanguages->isNotEmpty())
                <section class="surface-panel-alt rounded-lg border p-5" aria-labelledby="media-info-heading">
                    <h3 id="media-info-heading" class="flex items-center text-lg font-semibold text-gray-800 dark:text-gray-200">
                        <i aria-hidden="true" class="fas fa-photo-video mr-2 text-link dark:text-primary-400"></i> Media Information
                    </h3>

                    <dl class="mt-3 space-y-3 text-sm">
                        @if($videoParts !== [])
                            <div>
                                <dt class="flex items-center gap-2 font-medium text-gray-600 dark:text-gray-400">
                                    <i aria-hidden="true" class="fas fa-video text-primary-500 dark:text-primary-400"></i> Video Details
                                </dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ implode(' · ', $videoParts) }}</dd>
                            </div>
                        @endif

                        @if($audioTracks !== [])
                            <div>
                                <dt class="flex items-center gap-2 font-medium text-gray-600 dark:text-gray-400">
                                    <i aria-hidden="true" class="fas fa-volume-up text-primary-500 dark:text-primary-400"></i> Audio Details
                                    @if(count($audioTracks) > 1)
                                        <span class="text-xs font-normal">({{ count($audioTracks) }} tracks)</span>
                                    @endif
                                </dt>
                                <dd class="mt-1">
                                    <ol class="space-y-0.5 text-gray-900 dark:text-gray-100">
                                        @foreach($audioTracks as $track)
                                            <li>{{ implode(' · ', $track) }}</li>
                                        @endforeach
                                    </ol>
                                </dd>
                            </div>
                        @endif

                        @if($subtitleLanguages->isNotEmpty())
                            <div>
                                <dt class="flex items-center gap-2 font-medium text-gray-600 dark:text-gray-400">
                                    <i aria-hidden="true" class="fas fa-closed-captioning text-primary-500 dark:text-primary-400"></i> Subtitles
                                    <span class="text-xs font-normal">({{ $subtitleLanguages->count() }})</span>
                                </dt>
                                <dd class="mt-1.5 flex flex-wrap gap-1.5">
                                    @foreach($subtitleLanguages->take($visibleSubtitles) as $language)
                                        <span class="movie-meta-chip text-xs">{{ $language }}</span>
                                    @endforeach
                                    @if($subtitleLanguages->count() > $visibleSubtitles)
                                        <details class="w-full">
                                            <summary class="cursor-pointer text-xs font-medium text-link dark:text-primary-400">+{{ $subtitleLanguages->count() - $visibleSubtitles }} more</summary>
                                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                @foreach($subtitleLanguages->slice($visibleSubtitles) as $language)
                                                    <span class="movie-meta-chip text-xs">{{ $language }}</span>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </section>
            @endif
