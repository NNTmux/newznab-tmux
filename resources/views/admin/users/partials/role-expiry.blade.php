                <!-- Role Expiry Date -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-linear-to-br from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
                    <div class="flex items-center justify-between mb-3">
                        <label for="rolechangedate" class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                            <i class="fas fa-calendar-alt mr-2 text-primary-600 dark:text-primary-400"></i>
                            Role Expiry Date
                        </label>
                        @if(!empty($user->rolechangedate ?? ''))
                            @php
                                $expiryDate = \Carbon\Carbon::parse($user->rolechangedate);
                                $isExpired = $expiryDate->isPast();
                                $daysUntilExpiry = abs($expiryDate->diffInDays(now()));
                                $totalHours = abs($expiryDate->diffInHours(now()));
                                $hoursUntilExpiry = abs($totalHours % 24);
                            @endphp
                            @if($isExpired)
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 animate-pulse">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Expired
                                </span>
                            @elseif($daysUntilExpiry <= 7)
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Expiring Soon
                                </span>
                            @elseif($daysUntilExpiry <= 30)
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                </span>
                            @else
                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                </span>
                            @endif
                        @else
                            <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                <i class="fas fa-infinity mr-1"></i> No Expiry
                            </span>
                        @endif
                    </div>

                    <!-- Hidden input for form submission -->
                    <input type="hidden" id="rolechangedate" name="rolechangedate" value="{{ is_array($user) ? ($user['rolechangedate'] ?? '') : (isset($user->rolechangedate) ? \Carbon\Carbon::parse($user->rolechangedate)->format('Y-m-d\TH:i:s') : '') }}">

                    <!-- Hidden field to store the ORIGINAL user expiry date for proper stacking calculations -->
                    <input type="hidden" id="original_user_expiry" value="{{ is_array($user) ? ($user['rolechangedate'] ?? '') : (isset($user->rolechangedate) ? \Carbon\Carbon::parse($user->rolechangedate)->format('Y-m-d\TH:i:s') : '') }}">

                    <!-- Custom DateTime Picker -->
                    <div class="grid grid-cols-5 gap-3">
                        <!-- Year Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fas fa-calendar-alt mr-1"></i>Year
                            </label>
                            <select id="expiry_year"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400 transition-all shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500">
                                <option value="">--</option>
                                @for($y = date('Y'); $y <= date('Y') + 50; $y++)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Month Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fas fa-calendar mr-1"></i>Month
                            </label>
                            <select id="expiry_month"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400 transition-all shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500">
                                <option value="">--</option>
                                <option value="01">Jan</option>
                                <option value="02">Feb</option>
                                <option value="03">Mar</option>
                                <option value="04">Apr</option>
                                <option value="05">May</option>
                                <option value="06">Jun</option>
                                <option value="07">Jul</option>
                                <option value="08">Aug</option>
                                <option value="09">Sep</option>
                                <option value="10">Oct</option>
                                <option value="11">Nov</option>
                                <option value="12">Dec</option>
                            </select>
                        </div>

                        <!-- Day Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fas fa-calendar-day mr-1"></i>Day
                            </label>
                            <select id="expiry_day"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400 transition-all shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500">
                                <option value="">--</option>
                                @for($d = 1; $d <= 31; $d++)
                                    <option value="{{ sprintf('%02d', $d) }}">{{ $d }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Hour Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fas fa-clock mr-1"></i>Hour
                            </label>
                            <select id="expiry_hour"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400 transition-all shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500">
                                <option value="">--</option>
                                @for($h = 0; $h <= 23; $h++)
                                    <option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Minute Selector -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                <i class="fas fa-hourglass-half mr-1"></i>Min
                            </label>
                            <select id="expiry_minute"
                                    class="w-full px-2 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 focus:border-primary-500 dark:focus:border-primary-400 transition-all shadow-sm hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500">
                                <option value="">--</option>
                                @for($m = 0; $m <= 59; $m++)
                                    <option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <!-- Current Selection Display -->
                    <div id="datetime_preview" class="mt-3 p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hidden">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-info-circle mr-2"></i>Selected:
                            </span>
                            <span id="datetime_display" class="text-base font-bold text-primary-600 dark:text-primary-400"></span>
                        </div>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="mt-3 space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-expiry-action="set" data-days="1" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-md hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all hover:scale-105">
                                <i class="fas fa-clock mr-1"></i> +1 Day
                            </button>
                            <button type="button" data-expiry-action="set" data-days="7" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-md hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all hover:scale-105">
                                <i class="fas fa-calendar-week mr-1"></i> +1 Week
                            </button>
                            <button type="button" data-expiry-action="set" data-days="30" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-md hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all hover:scale-105">
                                <i class="fas fa-calendar-alt mr-1"></i> +1 Month
                            </button>
                            <button type="button" data-expiry-action="set" data-days="90" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-md hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all hover:scale-105">
                                <i class="fas fa-calendar mr-1"></i> +3 Months
                            </button>
                            <button type="button" data-expiry-action="set" data-days="365" data-hours="0" class="px-3 py-1.5 text-xs font-medium text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800 rounded-md hover:bg-purple-100 dark:hover:bg-purple-900/50 transition-all hover:scale-105">
                                <i class="fas fa-calendar-check mr-1"></i> +1 Year
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="1" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fas fa-hourglass-start mr-1"></i> +1 Hour
                            </button>
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="6" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fas fa-hourglass-half mr-1"></i> +6 Hours
                            </button>
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="12" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fas fa-hourglass-end mr-1"></i> +12 Hours
                            </button>
                            <button type="button" data-expiry-action="set" data-days="0" data-hours="24" class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-100 dark:hover:bg-green-900/50 transition-all hover:scale-105">
                                <i class="fas fa-clock mr-1"></i> +24 Hours
                            </button>
                            <button type="button" data-expiry-action="end-of-day" class="px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-md hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-all hover:scale-105">
                                <i class="fas fa-moon mr-1"></i> End of Today
                            </button>
                            <button type="button" data-expiry-action="clear" class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-all hover:scale-105">
                                <i class="fas fa-times-circle mr-1"></i> Clear
                            </button>
                        </div>
                    </div>

                    <!-- Status Information -->
                    @if(!empty($user->rolechangedate ?? ''))
                        <div class="mt-3 p-3 rounded-lg {{ $isExpired ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : ($daysUntilExpiry <= 7 ? 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800' : 'bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800') }}">
                            <div class="flex items-start">
                                <i class="fa {{ $isExpired ? 'fa-exclamation-triangle text-red-600 dark:text-red-400' : ($daysUntilExpiry <= 7 ? 'fa-clock text-yellow-600 dark:text-yellow-400' : 'fa-info-circle text-primary-600 dark:text-primary-400') }} mt-0.5 mr-2"></i>
                                <div class="flex-1">
                                    <p class="text-sm font-medium {{ $isExpired ? 'text-red-800 dark:text-red-200' : ($daysUntilExpiry <= 7 ? 'text-yellow-800 dark:text-yellow-200' : 'text-primary-800 dark:text-primary-200') }}">
                                        @if($isExpired)
                                            Role expired {{ $expiryDate->diffForHumans() }}
                                        @else
                                            Role expires {{ $expiryDate->diffForHumans() }}
                                        @endif
                                    </p>
                                    <p class="text-xs {{ $isExpired ? 'text-red-700 dark:text-red-300' : ($daysUntilExpiry <= 7 ? 'text-yellow-700 dark:text-yellow-300' : 'text-primary-700 dark:text-primary-300') }} mt-1">
                                        <i class="fas fa-calendar-alt mr-1"></i>{{ $expiryDate->format('F j, Y') }}
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-clock mr-1"></i>{{ $expiryDate->format('g:i A') }}
                                    </p>
                                    @if($daysUntilExpiry <= 7 && !$isExpired)
                                        <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                            <i class="fas fa-hourglass-half mr-1"></i>{{ $daysUntilExpiry }} day{{ $daysUntilExpiry != 1 ? 's' : '' }} and {{ $hoursUntilExpiry }} hour{{ $hoursUntilExpiry != 1 ? 's' : '' }} remaining
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="mt-3 text-xs text-gray-600 dark:text-gray-400 flex items-center">
                            <i class="fas fa-lightbulb mr-1.5 text-yellow-500"></i>
                            <span>Leave empty for permanent role assignment, or use quick actions above to set an expiry date and time.</span>
                        </p>
                    @endif
                </div>
