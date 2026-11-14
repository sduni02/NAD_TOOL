<?php

namespace App\Http\Controllers;

use App\Models\ReferenceRecord;
use App\Models\MappingPreset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MappingController extends Controller
{
    /**
     * Default NAD target headers provided by user.
     */
    public const DEFAULT_NAD_HEADERS = [
        'ORG_NAME', 'ORG_NAME_L', 'ACADEMIC_COURSE_ID', 'COURSE_NAME', 'BATCH', 'COURSE_NAME_L',
        'STREAM', 'STREAM_L', 'SESSION', 'REGN_NO', 'RROLL', 'CNAME', 'GENDER', 'DOB',
        'FNAME', 'MNAME', 'PHOTO', 'MRKS_REC_STATUS', 'RESULT', 'YEAR', 'MONTH', 'DIVISION',
        'GRADE', 'PERCENT', 'DOI', 'SEM', 'EXAM_TYPE', 'TOT', 'TOT_MRKS', 'TOT_CREDIT',
        'TOT_CREDIT_POINTS', 'TOT_GRADE_POINTS', 'GRAND_TOT_MAX', 'GRAND_TOT_MRKS',
        'GRAND_TOT_CREDIT_POINTS', 'GRAND_TOT_CREDIT', 'CGPA', 'REMARKS', 'SGPA',
        'ABC_ACCOUNT_ID', 'TERM_TYPE', 'TOT_GRADE', 'GRAND_TOT_GRADE',
        'SUB1NM', 'SUB1', 'SUB1_TH_MAX', 'SUB1_PR_MAX', 'SUB1_CE_MAX', 'SUB1_TH_MRKS',
        'SUB1_PR_MRKS', 'SUB1_CE_MRKS', 'SUB1_TOT', 'SUB1_GRADE', 'SUB1_GRADE_POINTS',
        'SUB1_CREDIT', 'SUB1_CREDIT_POINTS', 'SUB1_REMARKS', 'SUB1_TOT_WRDS', 'SUB1_CREDIT_ELIGIBILITY',
        'AADHAAR_NAME', 'ADMISSION_YEAR', 'NCRF_LEVEL', 'EXAM_CENTRE'
    ];

    /**
     * Show the mapping workspace.
     */
    public function index(Request $request)
    {
        $totalRecords = ReferenceRecord::count();
        $totalStudents = ReferenceRecord::whereNotNull('student_key')->distinct('student_key')->count('student_key');
        
        // Fetch dynamic columns from reference file
        $columnsJson = DB::table('reference_metadata')->where('key', 'columns')->value('value');
        $refColumns = $columnsJson ? json_decode($columnsJson, true) : [];
        $studentIdCol = DB::table('reference_metadata')->where('key', 'student_id_column')->value('value');

        // Find max subjects any single student has
        $maxSubjects = 4;
        if ($totalStudents > 0) {
            $maxFound = ReferenceRecord::select('student_key', DB::raw('count(*) as count'))
                ->whereNotNull('student_key')
                ->groupBy('student_key')
                ->orderBy('count', 'desc')
                ->first();
            if ($maxFound && $maxFound->count > 0) {
                $maxSubjects = max(4, min(15, (int)$maxFound->count));
            }
        }

        // Active template headers (session or default)
        $headers = session('target_headers', self::DEFAULT_NAD_HEADERS);
        $mappingConfig = session('mapping_config', []);

        // If no mapping config exists yet or columns changed, run auto-matching
        if (empty($mappingConfig) && !empty($refColumns)) {
            $mappingConfig = $this->autoMatchColumns($headers, $refColumns);
            session(['mapping_config' => $mappingConfig]);
        }

        $availableSources = $this->getAvailableSourceFields($refColumns, $maxSubjects);
        $presets = MappingPreset::orderBy('name')->get();

        return view('mapping.index', compact(
            'totalRecords',
            'totalStudents',
            'maxSubjects',
            'headers',
            'mappingConfig',
            'availableSources',
            'presets',
            'refColumns',
            'studentIdCol'
        ));
    }

    /**
     * Upload custom template file (CSV or Excel) to extract target headers.
     */
    public function uploadTemplate(Request $request)
    {
        $request->validate([
            'template_file' => 'required|file|max:20480', // max 20MB
        ]);

        $file = $request->file('template_file');
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $headers = [];
            if (in_array($ext, ['csv', 'txt'])) {
                $handle = fopen($file->getRealPath(), 'r');
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                $firstRow = fgetcsv($handle);
                fclose($handle);
                if ($firstRow) {
                    $headers = array_filter(array_map('trim', $firstRow));
                }
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $firstRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, true, false);
                if (!empty($firstRow[0])) {
                    $headers = array_filter(array_map('trim', $firstRow[0]));
                }
            } else {
                return back()->with('error', 'Please upload a CSV or Excel (.xlsx, .xls) template file.');
            }

            if (empty($headers)) {
                return back()->with('error', 'Could not detect any headers in the uploaded template file.');
            }

            $headers = array_values($headers);
            session(['target_headers' => $headers]);

            $columnsJson = DB::table('reference_metadata')->where('key', 'columns')->value('value');
            $refColumns = $columnsJson ? json_decode($columnsJson, true) : [];
            session(['mapping_config' => $this->autoMatchColumns($headers, $refColumns)]);

            return redirect()->route('mapping.index')
                ->with('success', 'Loaded ' . count($headers) . ' columns from template! Smart auto-mapping applied.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error reading template file: ' . $e->getMessage());
        }
    }

    /**
     * Reset to standard NAD template headers.
     */
    public function resetToDefault(Request $request)
    {
        $expandCount = (int)$request->input('subjects', 1);
        $headers = self::DEFAULT_NAD_HEADERS;

        if ($expandCount > 1) {
            $headers = $this->generateExpandedHeaders($expandCount);
        }

        session(['target_headers' => $headers]);
        $columnsJson = DB::table('reference_metadata')->where('key', 'columns')->value('value');
        $refColumns = $columnsJson ? json_decode($columnsJson, true) : [];
        session(['mapping_config' => $this->autoMatchColumns($headers, $refColumns)]);

        return redirect()->route('mapping.index')
            ->with('success', "Loaded default NAD template configured with {$expandCount} subject(s).");
    }

    /**
     * Auto-expand SUB1 into SUB1..SUB{N} in current headers.
     */
    public function expandSubjects(Request $request)
    {
        $count = (int)$request->input('count', 4);
        if ($count < 1 || $count > 15) {
            $count = 4;
        }

        $headers = $this->generateExpandedHeaders($count);
        session(['target_headers' => $headers]);

        $columnsJson = DB::table('reference_metadata')->where('key', 'columns')->value('value');
        $refColumns = $columnsJson ? json_decode($columnsJson, true) : [];
        session(['mapping_config' => $this->autoMatchColumns($headers, $refColumns)]);

        return redirect()->route('mapping.index')
            ->with('success', "Template expanded to support up to {$count} subjects per student.");
    }

    /**
     * Save active mapping as a named preset in database.
     */
    public function savePreset(Request $request)
    {
        $request->validate([
            'preset_name' => 'required|string|max:100',
            'mapping' => 'required|array',
            'static_values' => 'nullable|array',
        ]);

        $headers = session('target_headers', self::DEFAULT_NAD_HEADERS);
        $mapping = $request->input('mapping', []);
        $statics = $request->input('static_values', []);

        $config = [];
        foreach ($headers as $col) {
            $config[$col] = [
                'source' => $mapping[$col] ?? 'empty',
                'static' => $statics[$col] ?? '',
            ];
        }

        MappingPreset::updateOrCreate(
            ['name' => trim($request->input('preset_name'))],
            [
                'template_headers' => $headers,
                'mapping_config' => $config,
            ]
        );

        session(['mapping_config' => $config]);

        return back()->with('success', "Preset '{$request->input('preset_name')}' saved successfully!");
    }

    /**
     * Load a saved preset from database.
     */
    public function loadPreset(Request $request)
    {
        $preset = MappingPreset::findOrFail($request->input('preset_id'));

        session(['target_headers' => $preset->template_headers]);
        session(['mapping_config' => $preset->mapping_config]);

        return redirect()->route('mapping.index')
            ->with('success', "Preset '{$preset->name}' loaded!");
    }

    /**
     * Live Preview: Generate first 5 student flattened rows.
     */
    public function preview(Request $request)
    {
        $headers = session('target_headers', self::DEFAULT_NAD_HEADERS);
        $mapping = $request->input('mapping', []);
        $statics = $request->input('static_values', []);

        $config = [];
        foreach ($headers as $col) {
            $config[$col] = [
                'source' => $mapping[$col] ?? 'empty',
                'static' => $statics[$col] ?? '',
            ];
        }
        session(['mapping_config' => $config]);

        $studentKeys = ReferenceRecord::select('student_key')
            ->whereNotNull('student_key')
            ->distinct()
            ->orderBy('id', 'asc')
            ->limit(5)
            ->pluck('student_key');

        $rows = [];
        foreach ($studentKeys as $key) {
            $studentRecords = ReferenceRecord::where('student_key', $key)->orderBy('id', 'asc')->get();
            $rows[] = $this->transformStudentToOneRow($studentRecords, $headers, $config);
        }

        return response()->json([
            'headers' => $headers,
            'rows' => $rows,
            'total_students' => ReferenceRecord::whereNotNull('student_key')->distinct('student_key')->count('student_key'),
        ]);
    }

    /**
     * Export All Students flattened into 1-row-per-student format (CSV or Excel).
     */
    public function export(Request $request)
    {
        $headers = session('target_headers', self::DEFAULT_NAD_HEADERS);
        $mapping = $request->input('mapping', []);
        $statics = $request->input('static_values', []);
        $format = $request->input('export_format', 'csv');

        $config = [];
        foreach ($headers as $col) {
            $config[$col] = [
                'source' => $mapping[$col] ?? 'empty',
                'static' => $statics[$col] ?? '',
            ];
        }
        session(['mapping_config' => $config]);

        $allKeys = ReferenceRecord::select('student_key')
            ->whereNotNull('student_key')
            ->distinct()
            ->orderBy('id', 'asc')
            ->pluck('student_key');

        if ($allKeys->isEmpty()) {
            return back()->with('error', 'No student records in database to export. Please upload reference data first.');
        }

        if ($format === 'xlsx') {
            return $this->exportExcel($headers, $allKeys, $config);
        }

        return $this->exportCsvStream($headers, $allKeys, $config);
    }

    /**
     * Stream CSV download for unlimited dynamic records.
     */
    private function exportCsvStream(array $headers, $studentKeys, array $config)
    {
        $filename = 'NAD_Export_' . date('Ymd_His') . '.csv';

        $callback = function () use ($headers, $studentKeys, $config) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $headers);

            foreach ($studentKeys->chunk(100) as $keyChunk) {
                $studentsData = ReferenceRecord::whereIn('student_key', $keyChunk)
                    ->orderBy('id', 'asc')
                    ->get()
                    ->groupBy('student_key');

                foreach ($keyChunk as $key) {
                    if (isset($studentsData[$key])) {
                        $studentRecords = $studentsData[$key];
                        $row = $this->transformStudentToOneRow($studentRecords, $headers, $config);
                        fputcsv($file, $row);
                    }
                }
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate Excel .xlsx download for dynamic records.
     */
    private function exportExcel(array $headers, $studentKeys, array $config)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NAD Marksheets');
        $sheet->fromArray([$headers], null, 'A1');

        $rowIndex = 2;
        foreach ($studentKeys->chunk(100) as $keyChunk) {
            $studentsData = ReferenceRecord::whereIn('student_key', $keyChunk)
                ->orderBy('id', 'asc')
                ->get()
                ->groupBy('student_key');

            $rowsBatch = [];
            foreach ($keyChunk as $key) {
                if (isset($studentsData[$key])) {
                    $studentRecords = $studentsData[$key];
                    $rowsBatch[] = $this->transformStudentToOneRow($studentRecords, $headers, $config);
                }
            }

            if (!empty($rowsBatch)) {
                $sheet->fromArray($rowsBatch, null, 'A' . $rowIndex);
                $rowIndex += count($rowsBatch);
            }
        }

        $filename = 'NAD_Export_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Flatten a student's multiple subject records into one single output row.
     */
    private function transformStudentToOneRow($records, array $headers, array $config): array
    {
        $primaryRec = $records->first();
        if (!$primaryRec) {
            return array_fill(0, count($headers), '');
        }

        $primaryData = $primaryRec->row_data ?? [];

        // Aggregate calculations across student's rows
        $totMarksMax = 0;
        $totMarksObtained = 0;
        $totCredits = 0;
        $totCreditPoints = 0;
        $anyFailed = false;

        foreach ($records as $rec) {
            $d = $rec->row_data ?? [];
            
            // Search for total / marks fields dynamically
            $mTot = $this->findNumericValueInRow($d, ['total_marks', 'tot_marks', 'max_marks', 'tot_max']);
            $mObt = $this->findNumericValueInRow($d, ['marks_obtained', 'tot_obt', 'marks', 'total_obtained']);
            $cVal = $this->findNumericValueInRow($d, ['subject_credit', 'credit', 'credits']);
            $cpVal = $this->findNumericValueInRow($d, ['credit_point', 'credit_points', 'cp']);

            $totMarksMax += $mTot;
            $totMarksObtained += $mObt;
            $totCredits += $cVal;
            $totCreditPoints += $cpVal;

            $gradeVal = strtoupper(trim((string)$this->findValueInRow($d, ['grade_obtained', 'grade', 'sub_grade'])));
            if ($gradeVal === 'F' || $gradeVal === 'FAIL') {
                $anyFailed = true;
            }
        }

        $sgpa = $totCredits > 0 ? round($totCreditPoints / $totCredits, 2) : 0;
        $percentage = $totMarksMax > 0 ? round(($totMarksObtained / $totMarksMax) * 100, 2) : 0;
        $overallResult = $anyFailed ? 'FAIL' : 'PASS';

        $division = 'PASS';
        if (!$anyFailed) {
            if ($percentage >= 60) {
                $division = 'FIRST';
            } elseif ($percentage >= 45) {
                $division = 'SECOND';
            } else {
                $division = 'THIRD';
            }
        }

        // Map values to each target column
        $row = [];
        foreach ($headers as $col) {
            $rule = $config[$col] ?? ['source' => 'empty', 'static' => ''];
            $src = $rule['source'] ?? 'empty';
            $val = '';

            if ($src === 'static') {
                $val = $rule['static'] ?? '';
            } elseif ($src === 'empty') {
                $val = '';
            } elseif ($src === 'agg_tot_marks') {
                $val = $totMarksMax > 0 ? (string)$totMarksMax : '';
            } elseif ($src === 'agg_marks_obtained') {
                $val = $totMarksObtained > 0 ? (string)$totMarksObtained : '';
            } elseif ($src === 'agg_total_credits') {
                $val = $totCredits > 0 ? (string)$totCredits : '';
            } elseif ($src === 'agg_total_credit_points') {
                $val = $totCreditPoints > 0 ? (string)$totCreditPoints : '';
            } elseif ($src === 'agg_sgpa') {
                $val = $sgpa > 0 ? number_format($sgpa, 2, '.', '') : '';
            } elseif ($src === 'agg_percentage') {
                $val = $percentage > 0 ? number_format($percentage, 2, '.', '') : '';
            } elseif ($src === 'agg_result') {
                $val = $overallResult;
            } elseif ($src === 'agg_division') {
                $val = $division;
            } elseif ($src === 'agg_subject_count') {
                $val = (string)$records->count();
            } elseif (str_starts_with($src, 'ref:')) {
                // Direct column from reference file (student-level)
                $colName = substr($src, 4);
                $val = (string)($primaryData[$colName] ?? '');
            } elseif (str_starts_with($src, 'sub_')) {
                // Dynamic subject field e.g. sub_1:subject_code
                // Format: sub_{index}:{ref_column_name}
                if (preg_match('/^sub_(\d+):(.*)$/', $src, $m)) {
                    $subIdx = ((int)$m[1]) - 1;
                    $refCol = $m[2];
                    $subRec = $records->get($subIdx);
                    if ($subRec && isset($subRec->row_data[$refCol])) {
                        $val = (string)$subRec->row_data[$refCol];
                    }
                }
            } elseif (str_starts_with($src, 'agg_sum:')) {
                // Sum a specific reference column across all student's rows
                $refCol = substr($src, 8);
                $sum = 0;
                foreach ($records as $r) {
                    $sum += (float)($r->row_data[$refCol] ?? 0);
                }
                $val = (string)$sum;
            } else {
                // Fallback direct key match
                $val = (string)($primaryData[$src] ?? '');
            }

            $row[] = $val;
        }

        return $row;
    }

    /**
     * Find numeric value from row given candidate column names.
     */
    private function findNumericValueInRow(array $data, array $candidates): float
    {
        foreach ($candidates as $cand) {
            foreach ($data as $k => $v) {
                if (strcasecmp(trim(str_replace(['_', ' '], '', $k)), str_replace(['_', ' '], '', $cand)) === 0) {
                    return (float)$v;
                }
            }
        }
        return 0;
    }

    /**
     * Find string value from row given candidate column names.
     */
    private function findValueInRow(array $data, array $candidates): string
    {
        foreach ($candidates as $cand) {
            foreach ($data as $k => $v) {
                if (strcasecmp(trim(str_replace(['_', ' '], '', $k)), str_replace(['_', ' '], '', $cand)) === 0) {
                    return (string)$v;
                }
            }
        }
        return '';
    }

    /**
     * Generate source options using the REAL columns in uploaded reference file.
     */
    private function getAvailableSourceFields(array $refColumns, int $maxSubjects): array
    {
        $options = [];

        // 1. Reference File Columns (Student-level)
        if (!empty($refColumns)) {
            $studentCols = [];
            foreach ($refColumns as $col) {
                $studentCols["ref:{$col}"] = "{$col} (from Ref File)";
            }
            $options['Reference File Columns (Student Fields)'] = $studentCols;
        }

        // 2. Computed Totals
        $options['Calculated Student Totals & Result'] = [
            'agg_tot_marks' => 'Total Max Marks (SUM across subjects)',
            'agg_marks_obtained' => 'Total Marks Obtained (SUM across subjects)',
            'agg_total_credits' => 'Total Credits (SUM across subjects)',
            'agg_total_credit_points' => 'Total Credit Points (SUM across subjects)',
            'agg_sgpa' => 'SGPA (Credit Points ÷ Credits)',
            'agg_percentage' => 'Percentage % (Obtained ÷ Max × 100)',
            'agg_result' => 'Overall Result (PASS / FAIL)',
            'agg_division' => 'Division (FIRST / SECOND / THIRD)',
            'agg_subject_count' => 'Number of Subjects for Student',
        ];

        // 3. Subject-Specific Fields (Sub 1..N)
        if (!empty($refColumns)) {
            for ($i = 1; $i <= $maxSubjects; $i++) {
                $subOpts = [];
                foreach ($refColumns as $col) {
                    $subOpts["sub_{$i}:{$col}"] = "Subject {$i}: {$col}";
                }
                $options["Subject {$i} Fields"] = $subOpts;
            }
        }

        // 4. Special
        $options['Special / Custom'] = [
            'static' => 'Fixed Static Text (Enter custom value)',
            'empty' => 'Empty / Blank Cell',
        ];

        return $options;
    }

    /**
     * Smart Auto-Match: intelligently pairs target headers with reference columns.
     */
    private function autoMatchColumns(array $headers, array $refColumns): array
    {
        $mapping = [];
        $lowerRefCols = [];
        foreach ($refColumns as $col) {
            $clean = strtolower(preg_replace('/[^a-z0-9]/', '', $col));
            $lowerRefCols[$clean] = $col;
        }

        foreach ($headers as $col) {
            $clean = strtoupper(trim($col));
            $cleanKey = strtolower(preg_replace('/[^a-z0-9]/', '', $col));
            $source = 'empty';
            $static = '';

            // Check if exact or close match exists in reference columns
            if (isset($lowerRefCols[$cleanKey])) {
                $source = "ref:" . $lowerRefCols[$cleanKey];
            }
            // TR Sheet direct columns match
            elseif (in_array($clean, ['TOT', 'GRAND_TOT_MRKS']) && isset($lowerRefCols['grandtotal'])) {
                $source = 'ref:' . $lowerRefCols['grandtotal'];
            } elseif (in_array($clean, ['PERCENT', 'PERCENTAGE']) && isset($lowerRefCols['percentage'])) {
                $source = 'ref:' . $lowerRefCols['percentage'];
            } elseif (in_array($clean, ['RESULT']) && isset($lowerRefCols['result'])) {
                $source = 'ref:' . $lowerRefCols['result'];
            } elseif (in_array($clean, ['SGPA', 'CGPA']) && isset($lowerRefCols['sgpa'])) {
                $source = 'ref:' . $lowerRefCols['sgpa'];
            } elseif (in_array($clean, ['TOT_CREDIT_POINTS', 'GRAND_TOT_CREDIT_POINTS']) && isset($lowerRefCols['totalcreditpoints'])) {
                $source = 'ref:' . $lowerRefCols['totalcreditpoints'];
            } elseif (in_array($clean, ['COURSE_NAME']) && isset($lowerRefCols['course'])) {
                $source = 'ref:' . $lowerRefCols['course'];
            }
            // Computed fields
            elseif (in_array($clean, ['TOT_MRKS', 'GRAND_TOT_MAX'])) {
                $source = 'agg_tot_marks';
            } elseif (in_array($clean, ['TOT', 'GRAND_TOT_MRKS'])) {
                $source = 'agg_marks_obtained';
            } elseif (in_array($clean, ['TOT_CREDIT', 'GRAND_TOT_CREDIT'])) {
                $source = 'agg_total_credits';
            } elseif (in_array($clean, ['TOT_CREDIT_POINTS', 'GRAND_TOT_CREDIT_POINTS'])) {
                $source = 'agg_total_credit_points';
            } elseif (in_array($clean, ['SGPA', 'CGPA'])) {
                $source = 'agg_sgpa';
            } elseif (in_array($clean, ['PERCENT', 'PERCENTAGE'])) {
                $source = 'agg_percentage';
            } elseif (in_array($clean, ['RESULT'])) {
                $source = 'agg_result';
            } elseif (in_array($clean, ['DIVISION'])) {
                $source = 'agg_division';
            }
            // Standard Student Field Aliases
            elseif (in_array($clean, ['REGN_NO', 'REG_NO', 'REG', 'REGNO', 'REGISTRATION_NO'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['regno', 'reg', 'regnno', 'registrationno']);
            } elseif (in_array($clean, ['RROLL', 'ROLL', 'ROLL_NO', 'ROLLNO', 'STUDENT_ROLL'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['rollno', 'roll', 'rroll', 'studentroll']);
            } elseif (in_array($clean, ['CNAME', 'NAME', 'STUDENT_NAME', 'CANDIDATE_NAME', 'AADHAAR_NAME'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['studentname', 'cname', 'name', 'candidatename']);
            } elseif (in_array($clean, ['DOB', 'DATE_OF_BIRTH', 'BIRTH_DATE'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['dateofbirth', 'dob', 'birthdate']);
            } elseif (in_array($clean, ['FNAME', 'FATHER_NAME', 'FATHERS_NAME'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['fathername', 'fname', 'fathersname']);
            } elseif (in_array($clean, ['MNAME', 'MOTHER_NAME', 'MOTHERS_NAME'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['mothername', 'mname', 'mothersname']);
            } elseif (in_array($clean, ['SESSION', 'EXAM_SESSION', 'BATCH'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['session', 'examsession', 'batch']);
            } elseif (in_array($clean, ['SEM', 'SEMESTER'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['semester', 'sem']);
            } elseif (in_array($clean, ['ACADEMIC_COURSE_ID', 'COURSE_CODE'])) {
                $source = $this->matchFirstRefCol($lowerRefCols, ['acoursecode', 'coursecode', 'courseid']);
            }
            // Subject Fields: SUB1, SUB2, etc.
            elseif (preg_match('/^SUB(\d+)(.*)$/', $clean, $matches)) {
                $subIdx = $matches[1];
                $subSuffix = $matches[2];

                // Check if direct TR sheet column exists in reference table
                $trColKey = match ($subSuffix) {
                    '', '_CODE' => "sub{$subIdx}code",
                    'NM', '_NAME' => "sub{$subIdx}name",
                    '_TH_MAX' => "sub{$subIdx}fullmarks",
                    '_TH_MRKS' => "sub{$subIdx}ese",
                    '_CE_MRKS' => "sub{$subIdx}sie",
                    '_TOT' => "sub{$subIdx}tot",
                    '_GRADE' => "sub{$subIdx}grade",
                    '_GRADE_POINTS' => "sub{$subIdx}points",
                    '_CREDIT' => "sub{$subIdx}credit",
                    '_CREDIT_POINTS' => "sub{$subIdx}creditpoints",
                    default => null,
                };

                if ($trColKey && isset($lowerRefCols[$trColKey])) {
                    $source = "ref:" . $lowerRefCols[$trColKey];
                } else {
                    $matchedCol = match ($subSuffix) {
                        '', '_CODE' => $this->findRefColName($lowerRefCols, ['subjectcode', 'subcode', 'papercode', 'code']),
                        'NM', '_NAME' => $this->findRefColName($lowerRefCols, ['subjectname', 'papername', 'subname', 'subjectcode', 'subcode']),
                        '_TH_MAX' => $this->findRefColName($lowerRefCols, ['externalmarks', 'thmax', 'theorymax']),
                        '_CE_MAX' => $this->findRefColName($lowerRefCols, ['internalmarks', 'cemax', 'intmax', 'cce']),
                        '_TOT_MAX' => $this->findRefColName($lowerRefCols, ['totalmarks', 'totmax', 'maxmarks']),
                        '_TH_MRKS' => $this->findRefColName($lowerRefCols, ['externalmarksobtained', 'thmarks', 'thobt', 'externalobtained']),
                        '_CE_MRKS' => $this->findRefColName($lowerRefCols, ['internalmarksobtained', 'cemarks', 'ceobt', 'internalobtained']),
                        '_TOT' => $this->findRefColName($lowerRefCols, ['marksobtained', 'totmarks', 'totalobtained', 'marks', 'tot']),
                        '_GRADE' => $this->findRefColName($lowerRefCols, ['gradeobtained', 'grade', 'subgrade']),
                        '_GRADE_POINTS' => $this->findRefColName($lowerRefCols, ['gradepoint', 'gradepoints', 'gp']),
                        '_CREDIT' => $this->findRefColName($lowerRefCols, ['subjectcredit', 'credit', 'credits']),
                        '_CREDIT_POINTS' => $this->findRefColName($lowerRefCols, ['creditpoint', 'creditpoints', 'cp']),
                        default => null,
                    };

                    if ($matchedCol) {
                        $source = "sub_{$subIdx}:{$matchedCol}";
                    }
                }
            }

            $mapping[$col] = [
                'source' => $source,
                'static' => $static,
            ];
        }

        return $mapping;
    }

    private function matchFirstRefCol(array $lowerRefCols, array $candidates): string
    {
        foreach ($candidates as $cand) {
            if (isset($lowerRefCols[$cand])) {
                return "ref:" . $lowerRefCols[$cand];
            }
        }
        return 'empty';
    }

    private function findRefColName(array $lowerRefCols, array $candidates): ?string
    {
        foreach ($candidates as $cand) {
            if (isset($lowerRefCols[$cand])) {
                return $lowerRefCols[$cand];
            }
        }
        return null;
    }

    /**
     * Generate expanded headers with Sub 1 up to Sub {count}.
     */
    private function generateExpandedHeaders(int $count): array
    {
        $basePre = [
            'ORG_NAME', 'ORG_NAME_L', 'ACADEMIC_COURSE_ID', 'COURSE_NAME', 'BATCH', 'COURSE_NAME_L',
            'STREAM', 'STREAM_L', 'SESSION', 'REGN_NO', 'RROLL', 'CNAME', 'GENDER', 'DOB',
            'FNAME', 'MNAME', 'PHOTO', 'MRKS_REC_STATUS', 'RESULT', 'YEAR', 'MONTH', 'DIVISION',
            'GRADE', 'PERCENT', 'DOI', 'SEM', 'EXAM_TYPE', 'TOT', 'TOT_MRKS', 'TOT_CREDIT',
            'TOT_CREDIT_POINTS', 'TOT_GRADE_POINTS', 'GRAND_TOT_MAX', 'GRAND_TOT_MRKS',
            'GRAND_TOT_CREDIT_POINTS', 'GRAND_TOT_CREDIT', 'CGPA', 'REMARKS', 'SGPA',
            'ABC_ACCOUNT_ID', 'TERM_TYPE', 'TOT_GRADE', 'GRAND_TOT_GRADE',
        ];

        $subHeaders = [];
        for ($i = 1; $i <= $count; $i++) {
            $subHeaders[] = "SUB{$i}NM";
            $subHeaders[] = "SUB{$i}";
            $subHeaders[] = "SUB{$i}_TH_MAX";
            $subHeaders[] = "SUB{$i}_PR_MAX";
            $subHeaders[] = "SUB{$i}_CE_MAX";
            $subHeaders[] = "SUB{$i}_TH_MRKS";
            $subHeaders[] = "SUB{$i}_PR_MRKS";
            $subHeaders[] = "SUB{$i}_CE_MRKS";
            $subHeaders[] = "SUB{$i}_TOT";
            $subHeaders[] = "SUB{$i}_GRADE";
            $subHeaders[] = "SUB{$i}_GRADE_POINTS";
            $subHeaders[] = "SUB{$i}_CREDIT";
            $subHeaders[] = "SUB{$i}_CREDIT_POINTS";
            $subHeaders[] = "SUB{$i}_REMARKS";
            $subHeaders[] = "SUB{$i}_TOT_WRDS";
            $subHeaders[] = "SUB{$i}_CREDIT_ELIGIBILITY";
        }

        $basePost = [
            'AADHAAR_NAME', 'ADMISSION_YEAR', 'NCRF_LEVEL', 'EXAM_CENTRE'
        ];

        return array_merge($basePre, $subHeaders, $basePost);
    }
}
