<?php

namespace App\Http\Controllers;

use App\Models\ReferenceRecord;
use App\Models\CandidateMasterRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MarksheetController extends Controller
{
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

        $search = $request->input('search');
        $query = ReferenceRecord::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('student_key', 'like', "%{$search}%")
                  ->orWhere('row_data', 'like', "%{$search}%");
            });
        }

        $records = $query->orderBy('id', 'asc')->paginate(20)->withQueryString();

        // Candidate Master (APAAR / Aadhaar 2nd File)
        $masterCount = CandidateMasterRecord::count();
        $masterColumnsJson = DB::table('candidate_master_metadata')->where('key', 'columns')->value('value');
        $masterColumns = $masterColumnsJson ? json_decode($masterColumnsJson, true) : [];
        $masterKeyCol = DB::table('candidate_master_metadata')->where('key', 'match_key_column')->value('value');
        $masterFilename = DB::table('candidate_master_metadata')->where('key', 'original_filename')->value('value');

        return view('marksheets.index', compact(
            'totalRecords',
            'totalStudents',
            'columns',
            'studentIdCol',
            'records',
            'search',
            'masterCount',
            'masterColumns',
            'masterKeyCol',
            'masterFilename'
        ));
    }

    /**
     * Upload CSV or Excel file with ANY number of columns.
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

            // Dynamic CSV / Excel import
            if (in_array($extension, ['csv', 'txt'])) {
                $result = $this->importDynamicCsv($filePath);
            } else {
                $result = $this->importDynamicExcel($filePath);
            }

            // Save detected columns and student ID column
            DB::table('reference_metadata')->insert([
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
            DB::table('reference_metadata')->truncate();
            session()->forget(['target_headers', 'mapping_config']);

            return redirect()->route('marksheets.index')
                ->with('success', 'Database cleared successfully! All reference records and columns removed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to clear database: ' . $e->getMessage());
        }
    }

    /**
     * Upload Candidate Master (2nd File containing APAAR ID, Aadhaar, etc.)
     */
    public function uploadCandidateMaster(Request $request)
    {
        $request->validate([
            'master_file' => 'required|file|max:51200',
        ]);

        $file = $request->file('master_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'])) {
            return back()->with('error', 'Please upload a CSV or Excel (.xlsx, .xls) file for Candidate Master.');
        }

        try {
            DB::beginTransaction();

            CandidateMasterRecord::truncate();
            DB::table('candidate_master_metadata')->truncate();

            $filePath = $file->getRealPath();
            $rows = [];

            if (in_array($ext, ['csv', 'txt'])) {
                $handle = fopen($filePath, 'r');
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            } else {
                $spreadsheet = IOFactory::load($filePath);
                $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            }

            if (empty($rows)) {
                throw new \Exception('Candidate Master file is empty.');
            }

            $rawHeaders = array_shift($rows);
            $columns = $this->sanitizeHeaders($rawHeaders);
            $matchKeyCol = $this->detectStudentIdColumn($columns);

            $batch = [];
            $now = now();
            foreach ($rows as $row) {
                if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                    continue;
                }

                $rowData = [];
                foreach ($columns as $idx => $colName) {
                    $rowData[$colName] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                }

                $matchKey = ($matchKeyCol && isset($rowData[$matchKeyCol]) && $rowData[$matchKeyCol] !== '')
                    ? trim((string)$rowData[$matchKeyCol])
                    : null;

                $batch[] = [
                    'match_key' => $matchKey,
                    'row_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 500) {
                    CandidateMasterRecord::insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                CandidateMasterRecord::insert($batch);
            }

            $totalCount = CandidateMasterRecord::count();

            DB::table('candidate_master_metadata')->insert([
                ['key' => 'columns', 'value' => json_encode($columns, JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'match_key_column', 'value' => $matchKeyCol, 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'original_filename', 'value' => $file->getClientOriginalName(), 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'total_count', 'value' => (string)$totalCount, 'created_at' => $now, 'updated_at' => $now],
            ]);

            DB::commit();

            return redirect()->route('marksheets.index')
                ->with('success', "Candidate Master uploaded! Loaded {$totalCount} candidates with " . count($columns) . " columns (Match Key: '{$matchKeyCol}').");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to upload Candidate Master: ' . $e->getMessage());
        }
    }

    /**
     * Clear Candidate Master (2nd File)
     */
    public function clearCandidateMaster()
    {
        try {
            CandidateMasterRecord::truncate();
            DB::table('candidate_master_metadata')->truncate();
            session()->forget(['mapping_config']);

            return redirect()->route('marksheets.index')
                ->with('success', 'Candidate Master (APAAR / Aadhaar file) cleared successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to clear Candidate Master: ' . $e->getMessage());
        }
    }

    /**
     * Set match key column for Candidate Master
     */
    public function setCandidateMasterKey(Request $request)
    {
        $col = $request->input('match_col');
        if (!$col) {
            return back()->with('error', 'Please select a valid column.');
        }

        DB::table('candidate_master_metadata')->updateOrInsert(
            ['key' => 'match_key_column'],
            ['value' => $col, 'updated_at' => now()]
        );

        $records = CandidateMasterRecord::all();
        foreach ($records as $rec) {
            $data = $rec->row_data;
            $val = isset($data[$col]) ? trim((string)$data[$col]) : null;
            $rec->match_key = ($val === '' ? null : $val);
            $rec->save();
        }

        return back()->with('success', "Updated Candidate Master match key to '{$col}'.");
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
