<div>
    <div class="mx-auto w-full [:where(&)]:max-w-7xl px-0 lg:px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-x-8">
            <div class="col-span-1 lg:col-span-12 space-y-6">
                <div class="space-y-1">
                    <flux:heading size="lg">
                        {{ __('Log Viewer') }}
                    </flux:heading>
                    <flux:text>
                        {{ __('Pantau dan analisis log aktivitas sistem untuk mengidentifikasi kesalahan, memecahkan masalah, dan memahami perilaku aplikasi. ') }}
                    </flux:text>
                </div>

                <div class="space-y-6">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                            <div class="col-span-1">
                                <flux:field>
                                    <flux:label>
                                        {{ __('Pilih Tanggal Log') }}
                                        <flux:tooltip content="Pilih file log berdasarkan tanggal. Secara otomatis akan memuat file untuk tanggal yang dipilih.">
                                            <flux:icon.question-mark-circle variant="micro" class="ms-1 text-zinc-500" />
                                        </flux:tooltip>
                                    </flux:label>
                                    <flux:input wire:model.live="selected_date" type="date" />
                                </flux:field>
                            </div>
                            <div class="col-span-1 lg:col-span-2">
                                <flux:field>
                                    <flux:label>{{ __('Cari di Log') }}</flux:label>
                                    <flux:input wire:model.live.debounce.300ms="search_query"
                                        placeholder="Cari pesan, error, atau causer..."
                                        type="text"
                                    />
                                </flux:field>
                            </div>
                            <div class="col-span-1">
                                <flux:field>
                                    <flux:label>{{ __('Filter Causer') }}</flux:label>
                                    <flux:select wire:model.live="filter_causer">
                                        <option value="">Semua Causer</option>
                                        @foreach($unique_causers as $causer)
                                            <option value="{{ $causer }}">{{ $causer }}</option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            </div>
                            <div class="col-span-1">
                                <flux:field>
                                    <flux:label>{{ __('Level') }}</flux:label>
                                    <flux:select wire:model.live="filter_level">
                                        <option value="">Semua Level</option>
                                        <option value="debug">DEBUG</option>
                                        <option value="info">INFO</option>
                                        <option value="notice">NOTICE</option>
                                        <option value="warning">WARNING</option>
                                        <option value="error">ERROR</option>
                                        <option value="critical">CRITICAL</option>
                                        <option value="alert">ALERT</option>
                                        <option value="emergency">EMERGENCY</option>
                                        <option value="unknown">UNKNOWN</option>
                                    </flux:select>
                                </flux:field>
                            </div>
                            <div class="col-span-1 flex space-x-2">
                                <flux:button icon="arrow-down-tray" wire:click="downloadLog" wire:loading.attr="disabled"></flux:button>
                                <flux:button icon="trash" wire:click="clearLog" wire:loading.attr="disabled" variant="danger">Bersihkan</flux:button>
                            </div>
                        </div>
                    </div>
                    
                    @if (session()->has('flash.banner'))
                        <div class="bg-{{ session('flash.bannerStyle') }}-100 text-{{ session('flash.bannerStyle') }}-800 p-3 rounded-md">
                            {{ session('flash.banner') }}
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="bg-white rounded-md shadow border">
                            <table class="w-full divide-y divide-gray-200 table-fixed">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-[8%]">Severity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-[17%]">Datetime</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-[20%]">Causer</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[54%]">Message</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-[10%]"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td colspan="5" class="py-2 px-3 bg-yellow-50">
                                            <flux:text size="sm" class="text-gray-600">
                                                Menampilkan entri {{ ($current_page - 1) * $log_lines_per_page + 1 }} - {{ min($current_page * $log_lines_per_page, $total_entries) }} dari {{ $total_entries }} total entri (File: logify-{{ $selected_date }}.log).
                                                Klik baris untuk melihat detail konteks.
                                            </flux:text>
                                        </td>
                                    </tr>
                                    @forelse($parsed_log_entries as $index => $entry)
                                        <tr class="hover:bg-gray-50 @if(in_array($entry['severity'], ['EMERGENCY', 'CRITICAL', 'ALERT'])) bg-red-100/50 @elseif($entry['severity'] === 'ERROR') bg-red-50/50 @elseif($entry['severity'] === 'WARNING') bg-yellow-50/50 @endif">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    @if(in_array($entry['severity'], ['EMERGENCY', 'CRITICAL', 'ALERT', 'ERROR'])) bg-red-100 text-red-800
                                                    @elseif($entry['severity'] === 'WARNING') bg-yellow-100 text-yellow-800
                                                    @elseif($entry['severity'] === 'NOTICE') bg-blue-100 text-blue-800
                                                    @elseif($entry['severity'] === 'INFO') bg-green-100 text-green-800
                                                    @else bg-gray-100 text-gray-800 @endif">
                                                    {{ $entry['severity'] }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ \Carbon\Carbon::parse($entry['datetime'])->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $entry['causer'] }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 text-wrap max-w-xl">
                                                {{ Str::limit($entry['message'], 150) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                @if(!empty($entry['context']) || !empty($entry['trace']))
                                                    <button wire:click="toggleExpand({{ $index }})" class="text-indigo-600 hover:text-indigo-900 focus:outline-none p-1 rounded-full hover:bg-indigo-100">
                                                        @if(in_array($index, $expanded_entries))
                                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        @else
                                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                                            </svg>
                                                        @endif
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                        @if(in_array($index, $expanded_entries))
                                            <tr class="bg-gray-700 text-gray-200">
                                                <td colspan="5" class="p-3 text-xs font-mono">
                                                    <div class="overflow-x-auto w-full">
                                                        @if(!empty($entry['context']))
                                                            <p class="font-bold text-gray-300 mb-1">Context:</p>
                                                            <pre class="bg-gray-800 p-3 rounded-md whitespace-pre-wrap">{{ json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        @endif
                                                        @if(!empty($entry['trace']))
                                                            <p class="font-bold text-gray-300 mt-3 mb-1">Stack Trace:</p>
                                                            <pre class="bg-gray-800 p-3 rounded-md whitespace-pre-wrap">{{ json_encode($entry['trace'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        @endif
                                                        @if(empty($entry['context']) && empty($entry['trace']))
                                                            <p class="text-gray-400">Tidak ada detail konteks atau stack trace untuk entri ini.</p>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                                Tidak ada entri log untuk tanggal {{ $selected_date }}.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($total_pages > 1)
                            <div class="flex justify-between items-center mt-4">
                                <flux:button wire:click="previousPage" :disabled="$current_page <= 1" variant="ghost">Sebelumnya</flux:button>
                                <span class="text-sm text-gray-700">Halaman {{ $current_page }} dari {{ $total_pages }} (Total Entri: {{ $total_entries }})</span>
                                <flux:button wire:click="nextPage" :disabled="$current_page >= $total_pages" variant="ghost">Selanjutnya</flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>