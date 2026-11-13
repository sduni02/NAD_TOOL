<?php

namespace App\Http\Controllers;

use App\Models\ReferenceRecord;
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

        return view('marksheets.index', compact(
            'totalRecords',
            'totalStudents',
            'columns',
            'studentIdCol',
            'records',
            'search'
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

        // Update student_key for all rows
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
                ->with('success', 'Database cleared successfully! You can now upload a new CSV/Excel file with any column format.');
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

        // Check BOM
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
     * Clean and preserve headers, deduplicating if needed.
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

            // Deduplicate if identical header names exist
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

        // Fallback to first column
        return $columns[0] ?? null;
    }
}
