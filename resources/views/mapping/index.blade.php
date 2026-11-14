<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapping Workspace - NAD Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased font-sans pb-28">

    <!-- Header Navigation -->
    <header class="bg-indigo-900 border-b border-indigo-800 text-white sticky top-0 z-40 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('marksheets.index') }}" class="flex items-center space-x-3 text-white hover:opacity-90 transition-opacity">
                    <div class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center shadow-inner">
                        <i class="fa-solid fa-network-wired text-lg text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight">NAD Tool</h1>
                        <p class="text-xs text-indigo-300">Format Mapping Workspace</p>
                    </div>
                </a>

                <!-- Tabs -->
                <nav class="hidden md:flex items-center space-x-1 pl-6 border-l border-indigo-800/80">
                    <a href="{{ route('marksheets.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-200 hover:text-white hover:bg-indigo-800 transition-colors">
                        <i class="fa-solid fa-database mr-1.5"></i> Reference DB ({{ number_format($totalRecords) }})
                    </a>
                    <a href="{{ route('mapping.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-800 text-white shadow-inner">
                        <i class="fa-solid fa-sliders mr-1.5"></i> Column Mapping Workspace
                    </a>
                </nav>
            </div>

            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $totalStudents > 0 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
                    <span class="w-2 h-2 mr-1.5 rounded-full {{ $totalStudents > 0 ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                    {{ $totalStudents > 0 ? number_format($totalStudents) . ' Students in DB' : 'No Reference Data' }}
                </span>
                <a href="{{ route('marksheets.index') }}" class="text-xs px-3 py-1.5 rounded-lg bg-indigo-800 hover:bg-indigo-700 text-indigo-100 transition-colors flex items-center">
                    <i class="fa-solid fa-arrow-left mr-1.5"></i> DB Manager
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start space-x-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5 text-lg"></i>
                <div class="flex-1 text-sm font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start space-x-3 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 mt-0.5 text-lg"></i>
                <div class="flex-1 text-sm font-medium">{{ session('error') }}</div>
            </div>
        @endif

        @if($totalStudents === 0)
            <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-circle-info text-2xl text-amber-600"></i>
                    <div>
                        <h4 class="font-bold text-sm">Reference Data is Empty</h4>
                        <p class="text-xs text-amber-700">Please upload your student marksheet CSV/Excel first so the mapping engine has data to populate.</p>
                    </div>
                </div>
                <a href="{{ route('marksheets.index') }}" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-colors whitespace-nowrap shadow-sm">
                    <i class="fa-solid fa-upload mr-1.5"></i> Upload Reference CSV
                </a>
            </div>
        @endif

        <!-- Workspace Controls Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-100 pb-5">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-diagram-project text-indigo-600 mr-2.5"></i>
                        Target Format Mapping Workspace
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Transform multi-row student marksheets into <strong>one single row per student</strong>. Select which source populates each target column.
                    </p>
                </div>

                <!-- Template & Subject Expanders -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Upload Custom Format -->
                    <button type="button" onclick="document.getElementById('templateModal').classList.remove('hidden')" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition-colors flex items-center shadow-sm">
                        <i class="fa-solid fa-file-arrow-up mr-1.5 text-indigo-600"></i> Upload Target File
                    </button>

                    <!-- Expand to 4, 6, or 8 Subjects -->
                    <div class="relative inline-block text-left" id="expandDropdownContainer">
                        <button type="button" onclick="toggleDropdown('expandMenu')" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition-colors flex items-center shadow-sm">
                            <i class="fa-solid fa-arrows-split-up-and-left mr-1.5"></i>
                            Expand Subjects
                            <i class="fa-solid fa-chevron-down ml-1.5 text-[10px]"></i>
                        </button>
                        <div id="expandMenu" class="hidden absolute right-0 mt-2 w-56 rounded-xl shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 p-2 space-y-1">
                            <div class="px-2 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Expand Sub 1 to:</div>
                            <form action="{{ route('mapping.expandSubjects') }}" method="POST">
                                @csrf
                                <input type="hidden" name="count" value="4">
                                <button type="submit" class="w-full text-left px-3 py-1.5 rounded-lg text-xs text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center justify-between">
                                    <span>4 Subjects (SUB1 to SUB4)</span>
                                    <span class="text-[10px] text-slate-400">Standard</span>
                                </button>
                            </form>
                            <form action="{{ route('mapping.expandSubjects') }}" method="POST">
                                @csrf
                                <input type="hidden" name="count" value="6">
                                <button type="submit" class="w-full text-left px-3 py-1.5 rounded-lg text-xs text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center justify-between">
                                    <span>6 Subjects (SUB1 to SUB6)</span>
                                </button>
                            </form>
                            <form action="{{ route('mapping.expandSubjects') }}" method="POST">
                                @csrf
                                <input type="hidden" name="count" value="8">
                                <button type="submit" class="w-full text-left px-3 py-1.5 rounded-lg text-xs text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 flex items-center justify-between">
                                    <span>8 Subjects (SUB1 to SUB8)</span>
                                    <span class="text-[10px] text-slate-400">Max in Ref</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Reset to NAD Default -->
                    <form action="{{ route('mapping.resetDefault') }}" method="POST" onsubmit="return confirm('Reset all mappings back to standard NAD format?');">
                        @csrf
                        <button type="submit" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 bg-slate-50 hover:bg-slate-100 border border-slate-200 transition-colors flex items-center">
                            <i class="fa-solid fa-rotate-left mr-1.5"></i> Reset Default
                        </button>
                    </form>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200/80 text-xs">
                <div>
                    <span class="text-slate-400 block font-medium">Target Columns:</span>
                    <span class="font-bold text-slate-800 text-sm font-mono">{{ count($headers) }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Mapped Columns:</span>
                    <span class="font-bold text-emerald-600 text-sm font-mono" id="mappedCountDisplay">
                        {{ count(array_filter($mappingConfig, fn($c) => ($c['source'] ?? 'empty') !== 'empty')) }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Ref DB Columns:</span>
                    <span class="font-bold text-purple-700 text-sm font-mono">{{ count($refColumns) }} Columns</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Student Group-By:</span>
                    <span class="font-bold text-indigo-700 text-sm truncate block" title="{{ $studentIdCol ?? 'None' }}">{{ $studentIdCol ?? 'None' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block font-medium">Students to Export:</span>
                    <span class="font-bold text-slate-800 text-sm font-mono">{{ number_format($totalStudents) }}</span>
                </div>
            </div>

            <!-- Presets & Quick Filter -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                <!-- Search in columns -->
                <div class="relative w-full sm:w-80">
                    <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="columnFilterInput" placeholder="Filter columns (e.g. SUB1, ROLL, MARKS)..." onkeyup="filterColumnRows(this.value)" class="w-full pl-8 pr-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                </div>

                <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                    <!-- Presets Selector -->
                    @if($presets->isNotEmpty())
                    <form action="{{ route('mapping.loadPreset') }}" method="POST" class="flex items-center space-x-1.5">
                        @csrf
                        <select name="preset_id" onchange="this.form.submit()" class="text-xs border border-slate-300 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">-- Load Saved Preset --</option>
                            @foreach($presets as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </form>
                    @endif

                    <button type="button" onclick="document.getElementById('savePresetModal').classList.remove('hidden')" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition-colors shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1.5 text-indigo-600"></i> Save Preset
                    </button>
                </div>
            </div>
        </div>

        <!-- Mapping Form & Table -->
        <form id="mappingForm" action="{{ route('mapping.export') }}" method="POST">
            @csrf
            <input type="hidden" name="export_format" id="exportFormatInput" value="csv">

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 bg-slate-100/70 border-b border-slate-200 flex items-center justify-between">
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center">
                        <i class="fa-solid fa-table-cells mr-2 text-indigo-600"></i>
                        Column Mapping Table
                    </div>
                    <div class="flex items-center space-x-2 text-xs">
                        <button type="button" onclick="setAllEmpty()" class="text-slate-500 hover:text-slate-800 underline">Clear All to Blank</button>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[650px] overflow-y-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-slate-50 border-b border-slate-200 shadow-sm z-10">
                            <tr class="text-slate-600 uppercase font-semibold text-[11px] tracking-wider">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4 w-64">Target Format Column</th>
                                <th class="py-3 px-4 w-80">Populate From (Reference DB Field)</th>
                                <th class="py-3 px-4">Static Value (if Fixed Text selected)</th>
                                <th class="py-3 px-4 w-28 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="mappingTableBody">
                            @foreach($headers as $index => $col)
                                @php
                                    $current = $mappingConfig[$col] ?? ['source' => 'empty', 'static' => ''];
                                    $currSrc = $current['source'] ?? 'empty';
                                    $currStatic = $current['static'] ?? '';
                                    $isMapped = ($currSrc !== 'empty');
                                @endphp
                                <tr class="hover:bg-indigo-50/30 transition-colors mapping-row" data-column="{{ strtolower($col) }}">
                                    <!-- Index -->
                                    <td class="py-3 px-4 text-center font-mono text-slate-400 text-[11px]">
                                        {{ $index + 1 }}
                                    </td>

                                    <!-- Column Name -->
                                    <td class="py-3 px-4 font-mono font-semibold text-slate-900">
                                        <div class="flex items-center space-x-2">
                                            @if(str_starts_with($col, 'SUB'))
                                                <span class="w-2 h-2 rounded-full bg-purple-500 shrink-0"></span>
                                            @elseif(in_array($col, ['RROLL', 'REGN_NO', 'CNAME', 'DOB', 'FNAME', 'MNAME']))
                                                <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                                            @elseif(str_contains($col, 'TOT') || in_array($col, ['SGPA', 'PERCENT', 'RESULT', 'GRADE']))
                                                <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                            @else
                                                <span class="w-2 h-2 rounded-full bg-slate-300 shrink-0"></span>
                                            @endif
                                            <span title="{{ $col }}">{{ $col }}</span>
                                        </div>
                                    </td>

                                    <!-- Source Dropdown -->
                                    <td class="py-3 px-4">
                                        <select name="mapping[{{ $col }}]" onchange="onSourceChanged(this, 'static_{{ $index }}', 'badge_{{ $index }}')" class="w-full rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-medium text-slate-800 shadow-sm source-select">
                                            @foreach($availableSources as $groupLabel => $opts)
                                                <optgroup label="{{ $groupLabel }}">
                                                    @foreach($opts as $val => $label)
                                                        <option value="{{ $val }}" {{ $currSrc === $val ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </td>

                                    <!-- Static Value Box -->
                                    <td class="py-3 px-4">
                                        <input type="text" name="static_values[{{ $col }}]" id="static_{{ $index }}" value="{{ $currStatic }}" placeholder="Enter constant text..." class="w-full rounded-xl border border-slate-300 py-1.5 px-3 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-slate-800 {{ $currSrc === 'static' ? '' : 'opacity-40 bg-slate-50' }}" {{ $currSrc === 'static' ? '' : 'readonly' }}>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="py-3 px-4 text-center" id="badge_{{ $index }}">
                                        @if($currSrc === 'empty')
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-400">Blank</span>
                                        @elseif($currSrc === 'static')
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 font-mono">Static</span>
                                        @elseif(str_starts_with($currSrc, 'master:'))
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-pink-100 text-pink-800 font-mono">APAAR/Aadhaar</span>
                                        @elseif(str_starts_with($currSrc, 'sub_'))
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800 font-mono">Subject</span>
                                        @elseif(str_starts_with($currSrc, 'agg_'))
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 font-mono">Computed</span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 font-mono">Student</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sticky Bottom Action Footer -->
            <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 py-3 px-4 sm:px-8 z-30 shadow-2xl">
                <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center space-x-2 text-xs text-slate-500">
                        <i class="fa-solid fa-circle-check text-emerald-500 text-sm"></i>
                        <span>Ready to export <strong>{{ number_format($totalStudents) }}</strong> students into 1 single row per student</span>
                    </div>

                    <div class="flex items-center space-x-3 w-full sm:w-auto">
                        <!-- Live Preview Button -->
                        <button type="button" onclick="triggerPreview()" class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition-colors shadow-sm">
                            <i class="fa-solid fa-eye mr-2 text-indigo-600"></i>
                            Preview Mapped Data (First 5 Students)
                        </button>

                        <!-- Export CSV Button -->
                        <button type="button" onclick="triggerExport('csv')" class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition-all active:scale-95">
                            <i class="fa-solid fa-file-csv mr-2"></i>
                            Export CSV
                        </button>

                        <!-- Export Excel Button -->
                        <button type="button" onclick="triggerExport('xlsx')" class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-all active:scale-95">
                            <i class="fa-solid fa-file-excel mr-2"></i>
                            Export Excel (.xlsx)
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </main>

    <!-- Upload Custom Format Modal -->
    <div id="templateModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in duration-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="font-bold text-slate-900 text-base flex items-center">
                    <i class="fa-solid fa-file-import text-indigo-600 mr-2"></i>
                    Upload Target Format Template
                </h3>
                <button type="button" onclick="document.getElementById('templateModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>
            <form action="{{ route('mapping.uploadTemplate') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <p class="text-xs text-slate-500">
                    Upload any CSV or Excel file that contains your desired column headers in the first row. The system will extract the columns and auto-match fields.
                </p>
                <div class="border-2 border-dashed border-slate-300 hover:border-indigo-500 rounded-xl p-6 text-center cursor-pointer bg-slate-50" onclick="document.getElementById('modalFileInput').click()">
                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                    <div class="text-xs font-semibold text-slate-700" id="modalFileLabel">Click to select CSV or Excel template</div>
                    <div class="text-[11px] text-slate-400 mt-1">Accepts .csv, .xlsx, .xls</div>
                    <input type="file" name="template_file" id="modalFileInput" accept=".csv, .xlsx, .xls" class="sr-only" required onchange="document.getElementById('modalFileLabel').innerText = this.files[0]?.name || 'Selected'">
                </div>
                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button type="button" onclick="document.getElementById('templateModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">Upload & Load Columns</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Save Preset Modal -->
    <div id="savePresetModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-900 text-sm flex items-center">
                    <i class="fa-solid fa-floppy-disk text-indigo-600 mr-2"></i>
                    Save Current Mapping Configuration
                </h3>
                <button type="button" onclick="document.getElementById('savePresetModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>
            <p class="text-xs text-slate-500">Save your current column mappings so you can easily reload them anytime.</p>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Preset Name</label>
                <input type="text" id="presetNameInput" placeholder="e.g. NAD UG Semester 1 Format" class="w-full rounded-xl border border-slate-300 py-2 px-3 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="document.getElementById('savePresetModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="button" onclick="submitSavePreset()" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">Save Preset</button>
            </div>
        </div>
    </div>

    <!-- Live Preview Modal -->
    <div id="previewModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-6xl w-full h-[85vh] flex flex-col p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                <div>
                    <h3 class="font-bold text-slate-900 text-base flex items-center">
                        <i class="fa-solid fa-magnifying-glass-chart text-indigo-600 mr-2.5"></i>
                        Live Flattened Data Preview (First 5 Students)
                    </h3>
                    <p class="text-xs text-slate-500">Each student's multiple subject records are merged into 1 single row based on your mappings.</p>
                </div>
                <button type="button" onclick="document.getElementById('previewModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <!-- Preview Table Container -->
            <div class="flex-1 overflow-auto border border-slate-200 rounded-xl custom-scrollbar" id="previewTableContainer">
                <div class="p-12 text-center text-slate-400" id="previewLoading">
                    <i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-600 mb-2"></i>
                    <p class="text-xs">Generating student data rows...</p>
                </div>
                <table class="w-full text-left border-collapse text-[11px] hidden" id="previewTable">
                    <thead class="sticky top-0 bg-slate-100 border-b border-slate-300 font-bold text-slate-700 font-mono shadow-sm" id="previewTableHead"></thead>
                    <tbody class="divide-y divide-slate-200 font-sans" id="previewTableBody"></tbody>
                </table>
            </div>

            <div class="flex items-center justify-between pt-2 text-xs">
                <span class="text-slate-500" id="previewStudentCount">Showing 5 students</span>
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="document.getElementById('previewModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">Close Preview</button>
                    <button type="button" onclick="document.getElementById('previewModal').classList.add('hidden'); triggerExport('csv')" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-sm">Export Full CSV</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Source Change Handler
        function onSourceChanged(select, staticInputId, badgeId) {
            const staticInput = document.getElementById(staticInputId);
            const badge = document.getElementById(badgeId);
            const val = select.value;

            if (val === 'static') {
                staticInput.removeAttribute('readonly');
                staticInput.classList.remove('opacity-40', 'bg-slate-50');
                staticInput.focus();
                badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 font-mono">Static</span>';
            } else {
                staticInput.setAttribute('readonly', 'true');
                staticInput.classList.add('opacity-40', 'bg-slate-50');
                if (val === 'empty') {
                    badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-400">Blank</span>';
                } else if (val.startsWith('master:')) {
                    badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-pink-100 text-pink-800 font-mono">APAAR/Aadhaar</span>';
                } else if (val.startsWith('sub_')) {
                    badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800 font-mono">Subject</span>';
                } else if (val.startsWith('agg_')) {
                    badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 font-mono">Computed</span>';
                } else {
                    badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 font-mono">Student</span>';
                }
            }
            updateMappedCount();
        }

        function updateMappedCount() {
            const selects = document.querySelectorAll('.source-select');
            let count = 0;
            selects.forEach(s => {
                if (s.value !== 'empty') count++;
            });
            const display = document.getElementById('mappedCountDisplay');
            if (display) display.innerText = count;
        }

        // Set all to blank
        function setAllEmpty() {
            if (!confirm('Set all column mappings to Blank?')) return;
            document.querySelectorAll('.source-select').forEach((s, idx) => {
                s.value = 'empty';
                const staticInput = document.getElementById('static_' + idx);
                const badge = document.getElementById('badge_' + idx);
                if (staticInput) {
                    staticInput.value = '';
                    staticInput.setAttribute('readonly', 'true');
                    staticInput.classList.add('opacity-40', 'bg-slate-50');
                }
                if (badge) {
                    badge.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-400">Blank</span>';
                }
            });
            updateMappedCount();
        }

        // Quick filter for column rows
        function filterColumnRows(query) {
            const q = query.toLowerCase().trim();
            const rows = document.querySelectorAll('.mapping-row');
            rows.forEach(r => {
                const col = r.getAttribute('data-column');
                if (!q || col.includes(q)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        }

        // Trigger Export (CSV or Excel)
        function triggerExport(format) {
            document.getElementById('exportFormatInput').value = format;
            document.getElementById('mappingForm').submit();
        }

        // Toggle dropdowns
        function toggleDropdown(id) {
            const el = document.getElementById(id);
            el.classList.toggle('hidden');
        }

        window.addEventListener('click', function(e) {
            const expandContainer = document.getElementById('expandDropdownContainer');
            const expandMenu = document.getElementById('expandMenu');
            if (expandContainer && !expandContainer.contains(e.target) && expandMenu) {
                expandMenu.classList.add('hidden');
            }
        });

        // Trigger Live Preview
        async function triggerPreview() {
            const modal = document.getElementById('previewModal');
            const loading = document.getElementById('previewLoading');
            const table = document.getElementById('previewTable');
            const thead = document.getElementById('previewTableHead');
            const tbody = document.getElementById('previewTableBody');
            
            modal.classList.remove('hidden');
            loading.classList.remove('hidden');
            table.classList.add('hidden');

            const form = document.getElementById('mappingForm');
            const formData = new FormData(form);

            try {
                const res = await fetch("{{ route('mapping.preview') }}", {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                const data = await res.json();
                
                // Build headers
                thead.innerHTML = '<tr class="bg-slate-200 text-slate-800">' + 
                    data.headers.map(h => '<th class="py-2.5 px-3 whitespace-nowrap border-r border-slate-300">' + h + '</th>').join('') + 
                    '</tr>';

                // Build rows
                if (data.rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="' + data.headers.length + '" class="p-8 text-center text-slate-400">No student records found in database. Please upload reference marksheet data first.</td></tr>';
                } else {
                    tbody.innerHTML = data.rows.map(row => {
                        return '<tr class="hover:bg-indigo-50/50 border-b border-slate-200">' + 
                            row.map(cell => '<td class="py-2 px-3 whitespace-nowrap border-r border-slate-100 font-mono text-[11px] text-slate-700">' + (cell || '<span class="text-slate-300">-</span>') + '</td>').join('') + 
                            '</tr>';
                    }).join('');
                }

                document.getElementById('previewStudentCount').innerText = 'Showing ' + data.rows.length + ' of ' + data.total_students + ' total students';
                loading.classList.add('hidden');
                table.classList.remove('hidden');
            } catch (err) {
                loading.innerHTML = '<div class="text-rose-600 font-bold">Failed to load preview: ' + err.message + '</div>';
            }
        }

        // Save preset
        function submitSavePreset() {
            const name = document.getElementById('presetNameInput').value.trim();
            if (!name) {
                alert('Please enter a name for the preset.');
                return;
            }

            const form = document.getElementById('mappingForm');
            const formData = new FormData(form);
            formData.append('preset_name', name);

            fetch("{{ route('mapping.savePreset') }}", {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            }).then(() => {
                alert("Preset '" + name + "' saved successfully!");
                document.getElementById('savePresetModal').classList.add('hidden');
                window.location.reload();
            }).catch(err => {
                alert('Error saving preset: ' + err.message);
            });
        }
    </script>
</body>
</html>
