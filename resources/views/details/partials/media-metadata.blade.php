            <!-- Video/Audio Metadata -->
            @if(!empty($reVideo) || !empty($reAudio) || !empty($reSubs))
                <div class="surface-panel-alt rounded-lg p-6 border">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                        <i class="fas fa-photo-video mr-2 text-primary-600 dark:text-primary-400"></i> Media Information
                    </h3>

                    @if(!empty($reVideo))
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <i class="fas fa-video mr-2 text-primary-500 dark:text-primary-400"></i> Video Details
                            </h4>
                            <div class="surface-panel rounded-lg p-4 shadow-sm border">
                                <dl class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    @if(!empty($reVideo['containerformat']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Container Format</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['containerformat'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videocodec']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Video Codec</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videocodec'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoformat']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Video Format</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videoformat'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videowidth']) && !empty($reVideo['videoheight']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Resolution</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videowidth'] }}x{{ $reVideo['videoheight'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoaspect']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Aspect Ratio</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videoaspect'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoframerate']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Frame Rate</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videoframerate'] }} fps</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videoduration']))
                                        @php
                                            $durationMs = intval($reVideo['videoduration']);
                                            $durationMinutes = $durationMs > 0 ? round($durationMs / 1000 / 60) : 0;
                                        @endphp
                                        @if($durationMinutes > 0)
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Duration</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $durationMinutes }} minutes</dd>
                                            </div>
                                        @endif
                                    @endif
                                    @if(!empty($reVideo['overallbitrate']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Bit Rate</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['overallbitrate'] }}</dd>
                                        </div>
                                    @endif
                                    @if(!empty($reVideo['videolibrary']))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Encoder Library</dt>
                                            <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reVideo['videolibrary'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    @endif

                    @if(!empty($reAudio))
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <i class="fas fa-volume-up mr-2 text-primary-500 dark:text-primary-400"></i> Audio Details
                            </h4>
                            @foreach($reAudio as $index => $audio)
                                <div class="surface-panel rounded-lg p-4 shadow-sm border {{ $index > 0 ? 'mt-3' : '' }}">
                                    @if(count($reAudio) > 1)
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Track {{ $index + 1 }}</p>
                                    @endif
                                    <dl class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                        @if(!empty($audio['audioformat']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Audio Format</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audioformat'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiocodec']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Codec</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiocodec'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiochannels']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Channels</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiochannels'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiobitrate']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Bit Rate</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiobitrate'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiolanguage']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Language</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiolanguage'] }}</dd>
                                            </div>
                                        @endif
                                        @if(!empty($audio['audiosamplerate']))
                                            <div>
                                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Sample Rate</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $audio['audiosamplerate'] }} Hz</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($reSubs))
                        <div>
                            <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                <i class="fas fa-closed-captioning mr-2 text-primary-500"></i> Subtitles
                            </h4>
                            <div class="surface-panel rounded-lg p-4 shadow-sm">
                                <p class="text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $reSubs->subs }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
