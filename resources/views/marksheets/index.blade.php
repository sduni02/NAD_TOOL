<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reference Database - NAD Tool</title>
    <!-- Tailwind CSS CDN -->
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
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased font-sans pb-16">

    <!-- Top Navigation Header -->
    <header class="bg-indigo-900 border-b border-indigo-800 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center shadow-inner">
                        <i class="fa-solid fa-database text-lg text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight">NAD Tool</h1>
                        <p class="text-xs text-indigo-300">Reference Database & Column Loader</p>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <nav class="hidden md:flex items-center space-x-1 pl-6 border-l border-indigo-800/80">
                    <a href="{{ route('marksheets.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-800 text-white shadow-inner">
                        <i class="fa-solid fa-database mr-1.5"></i> Reference DB ({{ number_format($totalRecords) }})
                    </a>
                    <a href="{{ route('mapping.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-200 hover:text-white hover:bg-indigo-800 transition-colors">
                        <i class="fa-solid fa-sliders mr-1.5"></i> Column Mapping Workspace
                    </a>
                </nav>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('mapping.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white shadow-sm transition-all flex items-center">
                    <i class="fa-solid fa-arrow-right-arrow-left mr-1.5"></i> Go to Mapping Workspace
                </a>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $totalRecords > 0 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700 text-slate-300' }}">
                    <span class="w-2 h-2 mr-1.5 rounded-full {{ $totalRecords > 0 ? 'bg-emerald-400 animate-pulse' : 'bg-slate-400' }}"></span>
                    {{ $totalRecords > 0 ? number_format($totalRecords) . ' rows loaded' : 'Database Empty' }}
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash Notifications -->
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

        <!-- Quick Summary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-table-list"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-800">{{ number_format($totalRecords) }}</div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Records</div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-800">{{ number_format($totalStudents) }}</div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Unique Students</div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-columns"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-800">{{ count($columns) }}</div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Detected Columns</div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-id-badge"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-800 truncate" title="{{ $studentIdCol ?? 'None' }}">
                        {{ $studentIdCol ?? 'None' }}
                    </div>
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Student ID Key</div>
                </div>
            </div>
        </div>

        <!-- Action Panel: Upload & Refresh Controls -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 border-b border-slate-100 pb-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center">
                        <i class="fa-solid fa-cloud-arrow-up text-indigo-600 mr-2"></i>
                        Upload Reference CSV / Excel (Any Column Format)
                    </h2>
                    <p class="text-sm text-slate-500">
                        Upload any file with 10, 50, 100, or more columns. Click <strong>Refresh Database</strong> anytime to wipe and upload a different format.
                    </p>
                </div>
                
                <!-- Refresh / Reset Database Form -->
                <div>
                    <form action="{{ route('marksheets.refresh') }}" method="POST" onsubmit="return confirm('⚠️ Are you sure you want to RESET & DELETE the entire database? All current reference data and columns will be removed.');">
                        @csrf
                        <button type="submit" 
                            class="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 hover:border-rose-300 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 active:scale-95 {{ $totalRecords === 0 ? 'opacity-60 cursor-not-allowed' : '' }}"
                            {{ $totalRecords === 0 ? 'disabled' : '' }}>
                            <i class="fa-solid fa-trash-can mr-2"></i>
                            Refresh Database (Delete All Data)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Upload Form -->
            <form action="{{ route('marksheets.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                    <div class="lg:col-span-2">
                        <label for="fileInput" class="relative border-2 border-dashed border-slate-300 hover:border-indigo-500 rounded-xl p-6 flex flex-col items-center justify-center text-center cursor-pointer transition-colors bg-slate-50 hover:bg-indigo-50/30 group">
                            <i class="fa-solid fa-file-excel text-3xl text-slate-400 group-hover:text-indigo-600 mb-2 transition-colors"></i>
                            <span class="text-sm font-semibold text-slate-700 group-hover:text-indigo-600" id="fileLabel">
                                Choose Reference CSV or Excel file (.csv, .xlsx, .xls)
                            </span>
                            <span class="text-xs text-slate-400 mt-1" id="fileSubtext">
                                Dynamic column support &bull; Any column count (20, 50, 100+ columns)
                            </span>
                            <input id="fileInput" name="file" type="file" accept=".csv, .xlsx, .xls, .txt" class="sr-only" required onchange="handleFileSelected(this)">
                        </label>
                    </div>

                    <div class="space-y-3">
                        <button type="submit" id="submitBtn" class="w-full flex items-center justify-center px-5 py-3.5 rounded-xl font-semibold text-sm text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/30 active:scale-[0.98]">
                            <i class="fa-solid fa-file-import mr-2"></i>
                            <span>Upload & Store into DB</span>
                        </button>
                        <p class="text-xs text-slate-400 text-center">
                            Replaces current DB and automatically imports all columns.
                        </p>
                    </div>
                </div>
            </form>
        </div>

        @if(!empty($columns))
        <!-- Student Identifier Column Switcher -->
        <div class="bg-indigo-50/70 border border-indigo-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <i class="fa-solid fa-users-gear text-indigo-600 text-xl"></i>
                <div>
                    <h4 class="text-xs font-bold text-indigo-900">Student Group-By Column:</h4>
                    <p class="text-[11px] text-indigo-700">Choose which column uniquely identifies each student (used to merge multiple rows into one row per student).</p>
                </div>
            </div>
            <form action="{{ route('marksheets.setStudentColumn') }}" method="POST" class="flex items-center space-x-2">
                @csrf
                <select name="student_col" class="text-xs border border-indigo-300 rounded-xl px-3 py-1.5 bg-white text-indigo-950 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm">
                    @foreach($columns as $col)
                        <option value="{{ $col }}" {{ $studentIdCol === $col ? 'selected' : '' }}>
                            {{ $col }} {{ $studentIdCol === $col ? '(Current)' : '' }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="px-3 py-1.5 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-sm transition-colors">
                    Update
                </button>
            </form>
        </div>
        @endif

        <!-- Stored Records Dynamic Preview Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50">
                <div class="flex items-center space-x-3">
                    <h3 class="font-bold text-slate-900">Reference Database Preview</h3>
                    <span class="text-xs bg-slate-200 text-slate-700 px-2 py-0.5 rounded-md font-semibold">
                        {{ $records->total() }} total rows
                    </span>
                    @if(!empty($columns))
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-md font-semibold">
                        {{ count($columns) }} columns
                    </span>
                    @endif
                </div>

                @if($totalRecords > 0)
                <!-- Search Form -->
                <form action="{{ route('marksheets.index') }}" method="GET" class="flex items-center space-x-2 w-full sm:w-80">
                    <div class="relative w-full">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search any column value..." class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm">
                    </div>
                    @if($search)
                        <a href="{{ route('marksheets.index') }}" class="px-2.5 py-1.5 text-xs text-slate-600 bg-slate-200 hover:bg-slate-300 rounded-lg font-medium transition-colors">Clear</a>
                    @endif
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">Search</button>
                </form>
                @endif
            </div>

            @if($records->count() > 0 && !empty($columns))
                <div class="overflow-x-auto max-h-[600px] overflow-y-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-slate-100/90 border-b border-slate-200 uppercase font-semibold text-[11px] tracking-wider text-slate-600 shadow-sm z-10 backdrop-blur-sm">
                            <tr>
                                <th class="py-3 px-4 w-12 text-center border-r border-slate-200">#</th>
                                @foreach($columns as $col)
                                    <th class="py-3 px-4 whitespace-nowrap border-r border-slate-200 {{ $studentIdCol === $col ? 'bg-indigo-50 text-indigo-900 font-bold' : '' }}">
                                        {{ $col }}
                                        @if($studentIdCol === $col)
                                            <span class="ml-1 text-[10px] text-indigo-600 font-normal">(ID)</span>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-mono text-[11px]">
                            @foreach($records as $rec)
                                @php
                                    $data = $rec->row_data ?? [];
                                @endphp
                                <tr class="hover:bg-indigo-50/40 transition-colors">
                                    <td class="py-2.5 px-4 text-center font-mono text-slate-400 border-r border-slate-100">
                                        {{ $rec->id }}
                                    </td>
                                    @foreach($columns as $col)
                                        <td class="py-2.5 px-4 whitespace-nowrap border-r border-slate-100 {{ $studentIdCol === $col ? 'bg-indigo-50/30 font-semibold text-indigo-950' : '' }}">
                                            {{ $data[$col] ?? '' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                    {{ $records->links() }}
                </div>
            @else
                <div class="py-16 text-center text-slate-400 space-y-3">
                    <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-300 flex items-center justify-center mx-auto text-2xl">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    @if($search)
                        <div class="font-semibold text-slate-600">No records found matching "{{ $search }}"</div>
                        <a href="{{ route('marksheets.index') }}" class="text-xs text-indigo-600 hover:underline">Reset Search Filter</a>
                    @else
                        <div class="font-semibold text-slate-600">No data currently in the database</div>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                            Upload any reference CSV or Excel file above to populate the local database.
                        </p>
                    @endif
                </div>
            @endif
        </div>

    </main>

    <script>
        function handleFileSelected(input) {
            const label = document.getElementById('fileLabel');
            const subtext = document.getElementById('fileSubtext');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                label.innerHTML = '<span class="text-indigo-600 font-bold"><i class="fa-solid fa-file mr-1"></i> ' + file.name + '</span>';
                subtext.innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB • Ready to upload';
            }
        }

        document.getElementById('uploadForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Importing to Database...';
            btn.classList.add('opacity-75', 'cursor-wait');
        });
    </script>
</body>
</html>
