<?php

namespace App\Http\Controllers;

use App\Models\ReferenceRecord;
use App\Services\TrSheetParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MarksheetController extends Controller
{
    protected TrSheetParser $trParser;

    public function __construct(TrSheetParser $trParser)
    {
        $this->trParser = $trParser;
    }

    /**
     * Show main dashboard with dynamic table preview and stats.
     */
    public function index(Request $request)
    {
        $totalRecords = ReferenceRecord::count();
        
        // Retrieve dynamic column headers
        $columnsJson = DB::table('reference_metadata')->where('key', 'columns')->value('value');
        $columns = $columnsJson ? json_decode($columnsJson, true) : [];

        // Student ID column
        $studentIdCol = DB::table('reference_metadata')->where('key', 'student_id_column')->value('value');
        $totalStudents = ReferenceRecord::whereNotNull('student_key')->distinct('student_key')->count('student_key');

        // TR Sheet Metadata (if applicable)
        $isTrSheet = DB::table('reference_metadata')->where('key', 'is_tr_sheet')->value('value') === '1';
        $activeTrSemester = DB::table('reference_metadata')->where('key', 'active_tr_semester')->value('value');
        $availableTrSemestersJson = DB::table('reference_metadata')->where('key', 'tr_available_semesters')->value('value');
        $availableTrSemesters = $availableTrSemestersJson ? json_decode($availableTrSemestersJson, true) : [];
        $trCourse = DB::table('reference_metadata')->where('key', 'tr_course')->value('value');
        $trSession = DB::table('reference_metadata')->where('key', 'tr_session')->value('value');

        $search = $request->input('search');
        $query = ReferenceRecord::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_key', 'like', "%{$search}%")
                  ->orWhere('row_data', 'like', "%{$search}%");
            });
        }

        $records = $query->orderBy('id', 'asc')->paginate(20)->withQueryString();

        return view('marksheets.index', compact(
            'totalRecords',
            'totalStudents',
            'columns',
            'studentIdCol',
            'records',
            'search',
            'isTrSheet',
            'activeTrSemester',
            'availableTrSemesters',
            'trCourse',
            'trSession'
        ));
    }

    /**
     * Upload CSV or Excel file (Standard or T.R. Sheet).
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // max 50MB
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return back()->with('error', 'Invalid file type. Please upload a .csv, .xlsx, or .xls file.');
        }

        $filePath = $file->getRealPath();

        try {
            DB::beginTransaction();

            // Clear previous data for a fresh upload
            ReferenceRecord::truncate();
            DB::table('reference_metadata')->truncate();

            // Check if this is a Tabulation Register (T.R.) Sheet
            if ($this->trParser->isTrSheet($filePath)) {
                // Save file locally for semester switching
                $storedPath = storage_path('app/tr_current.' . $extension);
                @copy($filePath, $storedPath);

                $semesters = $this->trParser->getAvailableSemesters($filePath);
                $targetSem = $request->input('tr_semester') ?? ($semesters[0]['key'] ?? null);

                $parsed = $this->trParser->parseSemester($filePath, $targetSem);

                // Insert student records
                $batch = [];
                $now = now();
                foreach ($parsed['records'] as $recData) {
                    $studentKey = $recData['Roll_No'] ?: ($recData['Reg_No'] ?: null);
                    $batch[] = [
                        'student_key' => $studentKey,
                        'row_data' => json_encode($recData, JSON_UNESCAPED_UNICODE),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($batch)) {
                    ReferenceRecord::insert($batch);
                }

                // Metadata
                DB::table('reference_metadata')->insert([
                    ['key' => 'is_tr_sheet', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'tr_file_path', 'value' => $storedPath, 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'active_tr_semester', 'value' => $parsed['semester'], 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'tr_available_semesters', 'value' => json_encode($semesters, JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'tr_course', 'value' => $parsed['course'], 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'tr_session', 'value' => $parsed['session'], 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'columns', 'value' => json_encode($parsed['columns'], JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'student_id_column', 'value' => 'Roll_No', 'created_at' => $now, 'updated_at' => $now],
                    ['key' => 'original_filename', 'value' => $file->getClientOriginalName(), 'created_at' => $now, 'updated_at' => $now],
                ]);

                DB::commit();

                $semCount = count($semesters);
                $stCount = count($parsed['records']);
                return redirect()->route('marksheets.index')
                    ->with('success', "T.R. Sheet Detected! Loaded {$stCount} students for '{$parsed['semester']}' ({$semCount} semesters available).");
            }

            // Standard CSV / Excel import
            if (in_array($extension, ['csv', 'txt'])) {
                $result = $this->importDynamicCsv($filePath);
            } else {
                $result = $this->importDynamicExcel($filePath);
            }

            // Save detected columns and student ID column
            DB::table('reference_metadata')->insert([
                ['key' => 'is_tr_sheet', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'columns', 'value' => json_encode($result['columns'], JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'student_id_column', 'value' => $result['student_id_col'], 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'original_filename', 'value' => $file->getClientOriginalName(), 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::commit();

            $colCount = count($result['columns']);
            return redirect()->route('marksheets.index')
                ->with('success', "Success! Uploaded {$result['count']} rows with {$colCount} dynamic columns into the local database.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to import file: ' . $e->getMessage());
        }
    }

    /**
     * Switch active semester for a loaded TR Sheet.
     */
    public function switchTrSemester(Request $request)
    {
        $targetSem = $request->input('semester');
        $storedPath = DB::table('reference_metadata')->where('key', 'tr_file_path')->value('value');

        if (!$storedPath || !file_exists($storedPath)) {
            return back()->with('error', 'Original TR Sheet file not found. Please re-upload the file.');
        }

        try {
            DB::beginTransaction();

            ReferenceRecord::truncate();

            $parsed = $this->trParser->parseSemester($storedPath, $targetSem);

            $batch = [];
            $now = now();
            foreach ($parsed['records'] as $recData) {
                $studentKey = $recData['Roll_No'] ?: ($recData['Reg_No'] ?: null);
                $batch[] = [
                    'student_key' => $studentKey,
                    'row_data' => json_encode($recData, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($batch)) {
                ReferenceRecord::insert($batch);
            }

            DB::table('reference_metadata')->updateOrInsert(
                ['key' => 'active_tr_semester'],
                ['value' => $parsed['semester'], 'updated_at' => $now]
            );

            DB::table('reference_metadata')->updateOrInsert(
                ['key' => 'columns'],
                ['value' => json_encode($parsed['columns'], JSON_UNESCAPED_UNICODE), 'updated_at' => $now]
            );

            session()->forget(['mapping_config']);

            DB::commit();

            return redirect()->route('marksheets.index')
                ->with('success', "Switched database to '{$parsed['semester']}' (" . count($parsed['records']) . " students loaded).");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to switch semester: ' . $e->getMessage());
        }
    }

    /**
     * Set / change which column is used to group by student.
     */
    public function setStudentColumn(Request $request)
    {
        $col = $request->input('student_col');
        if (!$col) {
            return back()->with('error', 'Please select a valid column.');
        }

        DB::table('reference_metadata')->updateOrInsert(
            ['key' => 'student_id_column'],
            ['value' => $col, 'updated_at' => now()]
        );

        $records = ReferenceRecord::all();
        foreach ($records as $rec) {
            $data = $rec->row_data;
            $val = isset($data[$col]) ? trim((string)$data[$col]) : null;
            $rec->student_key = ($val === '' ? null : $val);
            $rec->save();
        }

        return back()->with('success', "Updated Student Group-By column to '{$col}'.");
    }

    /**
     * Delete/Clear all reference data in local database.
     */
    public function refresh()
    {
        try {
            ReferenceRecord::truncate();
            $storedPath = DB::table('reference_metadata')->where('key', 'tr_file_path')->value('value');
            if ($storedPath && file_exists($storedPath)) {
                @unlink($storedPath);
            }
            DB::table('reference_metadata')->truncate();
            session()->forget(['target_headers', 'mapping_config']);

            return redirect()->route('marksheets.index')
                ->with('success', 'Database cleared successfully! All reference records and semester data removed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to clear database: ' . $e->getMessage());
        }
    }

    /**
     * Parse and stream-insert dynamic CSV.
     */
    private function importDynamicCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Could not open CSV file.');
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            throw new \Exception('The CSV file is empty or missing headers.');
        }

        $columns = $this->sanitizeHeaders($rawHeaders);
        $studentIdCol = $this->detectStudentIdColumn($columns);

        $batch = [];
        $batchSize = 500;
        $totalInserted = 0;
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row, fn($val) => $val !== null && trim($val) !== ''))) {
                continue;
            }

            $rowData = [];
            foreach ($columns as $index => $colName) {
                $val = isset($row[$index]) ? trim((string)$row[$index]) : '';
                $rowData[$colName] = $val;
            }

            $studentKey = ($studentIdCol && isset($rowData[$studentIdCol]) && $rowData[$studentIdCol] !== '')
                ? $rowData[$studentIdCol]
                : null;

            $batch[] = [
                'student_key' => $studentKey,
                'row_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                ReferenceRecord::insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            ReferenceRecord::insert($batch);
            $totalInserted += count($batch);
        }

        fclose($handle);

        return [
            'count' => $totalInserted,
            'columns' => $columns,
            'student_id_col' => $studentIdCol,
        ];
    }

    /**
     * Parse and insert dynamic Excel.
     */
    private function importDynamicExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new \Exception('The Excel worksheet is empty.');
        }

        $rawHeaders = array_shift($rows);
        $columns = $this->sanitizeHeaders($rawHeaders);
        $studentIdCol = $this->detectStudentIdColumn($columns);

        $batch = [];
        $batchSize = 500;
        $totalInserted = 0;
        $now = now();

        foreach ($rows as $row) {
            if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                continue;
            }

            $rowData = [];
            foreach ($columns as $index => $colName) {
                $val = isset($row[$index]) ? trim((string)$row[$index]) : '';
                $rowData[$colName] = $val;
            }

            $studentKey = ($studentIdCol && isset($rowData[$studentIdCol]) && $rowData[$studentIdCol] !== '')
                ? $rowData[$studentIdCol]
                : null;

            $batch[] = [
                'student_key' => $studentKey,
                'row_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                ReferenceRecord::insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            ReferenceRecord::insert($batch);
            $totalInserted += count($batch);
        }

        return [
            'count' => $totalInserted,
            'columns' => $columns,
            'student_id_col' => $studentIdCol,
        ];
    }

    /**
     * Clean and preserve headers.
     */
    private function sanitizeHeaders(array $rawHeaders): array
    {
        $seen = [];
        $columns = [];

        foreach ($rawHeaders as $i => $h) {
            $name = trim((string)$h);
            if ($name === '') {
                $name = 'Column_' . ($i + 1);
            }

            if (isset($seen[$name])) {
                $seen[$name]++;
                $name = $name . '_' . $seen[$name];
            } else {
                $seen[$name] = 1;
            }

            $columns[$i] = $name;
        }

        return $columns;
    }

    /**
     * Heuristic to auto-detect student identifier column.
     */
    private function detectStudentIdColumn(array $columns): ?string
    {
        $patterns = [
            '/^r?roll(_?no)?$/i',
            '/^reg(n|istration)?(_?no)?$/i',
            '/^student(_?id)?$/i',
            '/^admission(_?no)?$/i',
            '/^enrol(l)?ment(_?no)?$/i',
            '/^candidate(_?id)?$/i',
            '/^id$/i',
        ];

        foreach ($patterns as $pattern) {
            foreach ($columns as $col) {
                if (preg_match($pattern, trim($col))) {
                    return $col;
                }
            }
        }

        return $columns[0] ?? null;
    }
}
