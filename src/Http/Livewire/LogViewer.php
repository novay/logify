<?php

namespace Novay\Logify\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Str;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogViewer extends Component
{
    public $selected_date;
    public $parsed_log_entries = [];
    public $log_lines_per_page = 25;
    public $current_page = 1;
    public $total_pages = 1;
    public $total_entries = 0;

    public $search_query = '';
    public $filter_level = '';
    public $filter_causer = ''; 
    public $unique_causers = []; 

    public $expanded_entries = [];

    protected $queryString = ['selected_date', 'current_page', 'search_query', 'filter_level', 'filter_causer'];

    public function mount()
    {
        if (empty($this->selected_date)) {
            $this->selected_date = now()->format('Y-m-d');
        }
        $this->parseAndFilterLogContent();
    }

    public function updatedSelectedDate()
    {
        $this->current_page = 1;
        $this->search_query = '';
        $this->filter_level = '';
        $this->filter_causer = ''; 
        $this->expanded_entries = []; 
        $this->parseAndFilterLogContent();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['current_page', 'search_query', 'filter_level', 'filter_causer'])) {
            $this->parseAndFilterLogContent();
        }
    }
    
    public function toggleExpand($index)
    {
        if (in_array($index, $this->expanded_entries)) {
            $this->expanded_entries = array_diff($this->expanded_entries, [$index]);
        } else {
            $this->expanded_entries[] = $index;
        }
    }

    private function parseAndFilterLogContent()
    {
        $this->parsed_log_entries = [];
        $this->total_entries = 0;
        $this->expanded_entries = [];
        $this->unique_causers = [];

        if (empty($this->selected_date)) {
            return;
        }

        $fileName = 'logify-' . $this->selected_date . '.log';
        $filePath = storage_path('logs/' . $fileName);

        if (!File::exists($filePath)) {
            $defaultFileName = 'laravel-' . $this->selected_date . '.log';
            $defaultFilePath = storage_path('logs/' . $defaultFileName);
            
            if (!File::exists($defaultFilePath)) {
                 session()->flash('flash.banner', "File log untuk tanggal '{$this->selected_date}' tidak ditemukan.");
                 session()->flash('flash.bannerStyle', 'warning');
                 return;
            }
            $filePath = $defaultFilePath;
            $fileName = $defaultFileName;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
             session()->flash('flash.banner', "Gagal membaca konten file log '{$fileName}'.");
             session()->flash('flash.bannerStyle', 'danger');
             return;
        }

        $allParsedEntries = collect();
        $isJsonLogFile = Str::startsWith($fileName, 'govrn-') || Str::startsWith($fileName, 'logify-');

        $causers = collect(); 
        foreach ($lines as $line) {
            $entry = $this->parseLogLine($line, $isJsonLogFile);
            if ($entry) {
                $allParsedEntries->push($entry);
                $causers->push($entry['causer']);
            }
        }
        
        $this->unique_causers = $causers->unique()->sort()->values()->toArray();
        if (!in_array('System', $this->unique_causers)) {
            array_unshift($this->unique_causers, 'System');
        }

        $filteredEntries = $allParsedEntries->filter(function ($entry) {
            $match = true;
            $searchLower = strtolower($this->search_query);

            if ($this->search_query) {
                $contextString = is_string($entry['context'] ?? '') ? strtolower($entry['context']) : strtolower(json_encode($entry['context'] ?? []));
                
                if (stripos(strtolower($entry['message'] ?? ''), $searchLower) === false &&
                    stripos($contextString, $searchLower) === false &&
                    stripos(strtolower($entry['trace'] ?? ''), $searchLower) === false &&
                    stripos(strtolower($entry['causer'] ?? ''), $searchLower) === false) {
                    $match = false;
                }
            }

            if ($this->filter_level && $match) {
                $entryLevelLower = strtolower($entry['severity'] ?? '');
                if ($entryLevelLower !== strtolower($this->filter_level)) {
                    $match = false;
                }
            }
            
            if ($this->filter_causer && $match) {
                $causer = $entry['causer'] ?? '';
                if ($causer !== $this->filter_causer) {
                    $match = false;
                }
            }

            return $match;
        });

        $this->total_entries = $filteredEntries->count();
        $this->total_pages = ceil($this->total_entries / $this->log_lines_per_page);

        if ($this->current_page > $this->total_pages && $this->total_pages > 0) {
            $this->current_page = $this->total_pages;
        } elseif ($this->total_pages === 0) {
            $this->current_page = 1;
        }

        $offset = ($this->current_page - 1) * $this->log_lines_per_page;
        $this->parsed_log_entries = $filteredEntries->slice($offset, $this->log_lines_per_page)->values()->toArray();
    }

    private function parseLogLine($line, $isJsonLogFile = false)
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }
        
        if ($isJsonLogFile || (Str::startsWith($line, '{') && Str::endsWith($line, '}'))) {
            $json = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $severity = $json['level_name'] ?? ($json['level'] ? Log::getLevelName($json['level']) : 'UNKNOWN');
                $context = $json['context'] ?? [];
                $trace = is_array($context) && isset($context['exception']) ? $context['exception'] : null;

                $causer = 'System';
                if (isset($json['user']['name'])) {
                    $causer = $json['user']['name'];
                } elseif (isset($json['context']['user']['name'])) {
                    $causer = $json['context']['user']['name'];
                }
                
                return [
                    'severity' => strtoupper($severity),
                    'datetime' => $json['datetime'] ?? 'N/A',
                    'env' => $json['channel'] ?? 'N/A',
                    'causer' => $causer,
                    'message' => $json['message'] ?? 'No Message',
                    'context' => $context,
                    'trace' => $trace,
                    'raw' => $line,
                ];
            }
        }
        
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)/s', $line, $matches)) {
            $datetime = $matches[1];
            $env = $matches[2];
            $severity = $matches[3];
            $fullMessage = $matches[4];

            $context = [];
            $message = $fullMessage;
            $trace = null;
            $causer = 'System';

            if (preg_match('/^(.*?)(\{.*\})$/s', $fullMessage, $subMatches)) {
                $message = $subMatches[1];
                $jsonContext = json_decode($subMatches[2], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $jsonContext;
                    if (is_array($context) && isset($context['exception'])) {
                        $trace = $context['exception'];
                    }
                    if (isset($context['user']['name'])) {
                        $causer = $context['user']['name'];
                    }
                }
            }

            return [
                'severity' => strtoupper($severity),
                'datetime' => $datetime,
                'env' => $env,
                'causer' => $causer,
                'message' => trim($message),
                'context' => $context,
                'trace' => $trace,
                'raw' => $line,
            ];
        }
        
        return [
            'severity' => 'UNKNOWN',
            'datetime' => Carbon::now()->toDateTimeString(),
            'env' => 'app',
            'causer' => 'System',
            'message' => $line,
            'context' => [],
            'trace' => null,
            'raw' => $line,
        ];
    }
    
    public function nextPage()
    {
        if ($this->current_page < $this->total_pages) {
            $this->current_page++;
        }
    }
    
    public function previousPage()
    {
        if ($this->current_page > 1) {
            $this->current_page--;
        }
    }
    
    public function goToPage($page)
    {
        if ($page >= 1 && $page <= $this->total_pages) {
            $this->current_page = $page;
        }
    }

    public function downloadLog()
    {
        if (empty($this->selected_date)) {
            session()->flash('flash.banner', 'Pilih tanggal untuk mengunduh file log.');
            session()->flash('flash.bannerStyle', 'warning');
            return;
        }
        
        $fileName = 'logify-' . $this->selected_date . '.log';
        $filePath = storage_path('logs/' . $fileName);
        
        if (!File::exists($filePath)) {
            $fileName = 'laravel-' . $this->selected_date . '.log';
            $filePath = storage_path('logs/' . $fileName);

            if (!File::exists($filePath)) {
                session()->flash('flash.banner', "File log untuk tanggal '{$this->selected_date}' tidak ditemukan.");
                session()->flash('flash.bannerStyle', 'danger');
                return;
            }
        }

        return response()->download($filePath, $fileName);
    }
    
    public function clearLog()
    {
        if (empty($this->selected_date)) {
            session()->flash('flash.banner', 'Pilih tanggal untuk membersihkan file log.');
            session()->flash('flash.bannerStyle', 'warning');
            return;
        }

        $fileName = 'logify-' . $this->selected_date . '.log';
        $filePath = storage_path('logs/' . $fileName);
        
        if (!File::exists($filePath)) {
            session()->flash('flash.banner', "File log untuk tanggal '{$this->selected_date}' tidak ditemukan.");
            session()->flash('flash.bannerStyle', 'danger');
            return;
        }

        try {
            File::put($filePath, '');
            $this->parsed_log_entries = [];
            $this->total_entries = 0;
            $this->total_pages = 1;
            $this->current_page = 1;
            $this->expanded_entries = [];

            session()->flash('flash.banner', "File log untuk tanggal '{$this->selected_date}' berhasil dibersihkan.");
            session()->flash('flash.bannerStyle', 'success');
        } catch (\Exception $e) {
            Log::error('Gagal membersihkan log: ' . $e->getMessage(), ['file' => $fileName]);
            session()->flash('flash.banner', 'Gagal membersihkan file log: ' . $e->getMessage());
            session()->flash('flash.bannerStyle', 'danger');
        }
    }

    public function render()
    {
        return view('logify::log-viewer');
    }
}