<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class TrSheetParser
{
    /**
     * Detect if a file is a Tabulation Register (T.R.) Sheet.
     */
    public function isTrSheet(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return false;

        $linesChecked = 0;
        $foundIndicator = false;

        while (($line = fgets($handle)) !== false && $linesChecked < 100) {
            $linesChecked++;
            if (stripos($line, 'Tabulated Result') !== false ||
                (stripos($line, 'Semester-') !== false && stripos($line, 'Subject Code') !== false) ||
                (stripos($line, 'Subject Code') !== false && stripos($line, 'ESE') !== false)) {
                $foundIndicator = true;
                break;
            }
        }

        fclose($handle);
        return $foundIndicator;
    }

    /**
     * Get all semester blocks available in the TR Sheet.
     */
    public function getAvailableSemesters(string $filePath): array
    {
        $rows = $this->readAllRows($filePath);
        $semesters = [];
        $currentSem = null;
        $currentCourse = '';
        $currentSession = '';

        foreach ($rows as $rIdx => $row) {
            $rowStr = implode(' ', array_filter($row));

            // Detect Course
            if (preg_match('/COURSE:\s*,?\s*([^,]+)/i', $rowStr, $m)) {
                $currentCourse = trim($m[1]);
            }
            // Detect Session
            if (preg_match('/Session-?\s*([0-9\-]+)/i', $rowStr, $m)) {
                $currentSession = trim($m[1]);
            }

            // Detect Semester
            if (preg_match('/(Semester-[IVXLCDM0-9]+(?:\s*\([^\)]+\))?)/i', $rowStr, $m)) {
                $semName = trim($m[1]);
                if (!isset($semesters[$semName])) {
                    $semesters[$semName] = [
                        'key' => $semName,
                        'label' => $semName,
                        'start_row' => $rIdx,
                        'course' => $currentCourse,
                        'session' => $currentSession,
                    ];
                }
            }
        }

        return array_values($semesters);
    }

    /**
     * Parse a specific semester from the TR sheet.
     */
    public function parseSemester(string $filePath, ?string $targetSemester = null): array
    {
        $rows = $this->readAllRows($filePath);
        $semesters = $this->getAvailableSemesters($filePath);

        if (empty($semesters)) {
            throw new \Exception('No semester sections found in TR Sheet.');
        }

        // Default to first semester if not specified
        $selectedSem = null;
        if ($targetSemester) {
            foreach ($semesters as $s) {
                if ($s['key'] === $targetSemester || stripos($s['key'], $targetSemester) !== false) {
                    $selectedSem = $s;
                    break;
                }
            }
        }
        if (!$selectedSem) {
            $selectedSem = $semesters[0];
        }

        $semKey = $selectedSem['key'];
        
        // Find boundaries for this semester
        $startRow = null;
        $endRow = count($rows);

        foreach ($semesters as $idx => $s) {
            if ($s['key'] === $semKey) {
                $startRow = $s['start_row'];
                if (isset($semesters[$idx + 1])) {
                    $endRow = $semesters[$idx + 1]['start_row'];
                }
                break;
            }
        }

        if ($startRow === null) {
            throw new \Exception("Could not find section for {$semKey}");
        }

        $semRows = array_slice($rows, $startRow, $endRow - $startRow);
        return $this->parseSemesterBlock($semRows, $semKey);
    }

    /**
     * Parse one semester's slice of rows.
     */
    private function parseSemesterBlock(array $blockRows, string $semName): array
    {
        $course = '';
        $session = '';
        $school = '';
        $subjectCodes = [];
        $subjectFullMarks = [];
        $subjectPassMarks = [];
        $subjectLegend = [];
        $subHeaderRowIdx = null;

        // 1. Scan metadata and header indices
        foreach ($blockRows as $idx => $row) {
            $rowStr = implode(' ', array_filter($row));

            if (preg_match('/NAME OF SCHOOL:-?\s*([^,]+)/i', $rowStr, $m)) {
                $school = trim($m[1]);
            }
            if (preg_match('/COURSE:\s*,?\s*([^,]+)/i', $rowStr, $m)) {
                $course = trim($m[1]);
            }
            if (preg_match('/Session-?\s*([0-9\-]+)/i', $rowStr, $m)) {
                $session = trim($m[1]);
            }

            // Subject Code row
            $isSubjectCodeRow = false;
            foreach ($row as $cell) {
                if (stripos(trim((string)$cell), 'Subject Code') !== false) {
                    $isSubjectCodeRow = true;
                    break;
                }
            }
            if ($isSubjectCodeRow && empty($subjectCodes)) {
                $subjectCodes = $this->extractSubjectCodes($row);
            }

            // Full marks
            if (stripos($rowStr, 'Full marks') !== false && empty($subjectFullMarks)) {
                $subjectFullMarks = $this->extractAlignedValues($row);
            }

            // Pass marks
            if (stripos($rowStr, 'Pass marks') !== false && empty($subjectPassMarks)) {
                $subjectPassMarks = $this->extractAlignedValues($row);
            }

            // Sub-column header row e.g. S.N, Reg. No, University Roll No, Student Name, ESE, SIE...
            if (stripos($rowStr, 'Reg. No') !== false || stripos($rowStr, 'University Roll No') !== false) {
                $subHeaderRowIdx = $idx;
            }

            // Subject Legend at bottom e.g. SUBJETCT CODE, SUBJECT NAME, Cr
            if (stripos($rowStr, 'SUBJETCT CODE') !== false || stripos($rowStr, 'SUBJECT CODE') !== false) {
                // Following rows are legend
                for ($legIdx = $idx + 1; $legIdx < count($blockRows); $legIdx++) {
                    $legRow = array_values(array_filter(array_map('trim', $blockRows[$legIdx])));
                    if (count($legRow) >= 2) {
                        $code = trim($legRow[0]);
                        $name = trim($legRow[1]);
                        $cr = isset($legRow[2]) ? trim($legRow[2]) : '';
                        if ($code !== '' && !is_numeric($code)) {
                            $subjectLegend[$code] = [
                                'name' => $name,
                                'cr' => $cr,
                            ];
                        }
                    }
                }
            }
        }

        if ($subHeaderRowIdx === null) {
            throw new \Exception('Could not detect student table header row (Reg. No / Roll No).');
        }

        // Determine column structure
        // Usually: S.N (0), Reg. No (1), Roll No (2), Student Name (3)
        // Then 7 columns per subject: ESE, SIE, Total, Credit, Points, Grade, Credit Points
        // Then summary: Total Credit Points, SGPA, Grand Total, Percentage, Result
        $dataRows = [];
        $numSubjects = count($subjectCodes);
        if ($numSubjects === 0) {
            $numSubjects = 6; // standard fallback
        }

        // 2. Parse student data rows
        for ($i = $subHeaderRowIdx + 1; $i < count($blockRows); $i++) {
            $row = $blockRows[$i];
            $rowStr = implode(' ', array_filter($row));

            // Stop if reached legend or empty block
            if (stripos($rowStr, 'SUBJETCT CODE') !== false || stripos($rowStr, 'SUBJECT CODE') !== false || stripos($rowStr, 'Tabulated Result') !== false) {
                break;
            }

            // Check if this row is the component max marks row (e.g. 50,-,50...)
            if (empty($row[0]) && empty($row[1]) && empty($row[2]) && empty($row[3])) {
                // If it only has numbers like 50, 75, skip
                continue;
            }

            // Check if row has valid student data
            $regNo = isset($row[1]) ? trim((string)$row[1]) : '';
            $rollNo = isset($row[2]) ? trim((string)$row[2]) : '';
            $name = isset($row[3]) ? trim((string)$row[3]) : '';
            $sn = isset($row[0]) ? trim((string)$row[0]) : '';

            // If all student identifiers are empty, skip
            if ($regNo === '' && $rollNo === '' && $name === '') {
                continue;
            }

            $record = [
                'S_N' => $sn,
                'Reg_No' => $regNo,
                'Roll_No' => $rollNo,
                'Student_Name' => $name,
                'Course' => $course,
                'Session' => $session,
                'Semester' => $semName,
            ];

            // Extract each subject's 7 components
            // Subject 1 starts at index 4
            $colPointer = 4;
            for ($sIdx = 1; $sIdx <= $numSubjects; $sIdx++) {
                $subCode = $subjectCodes[$sIdx - 1]['code'] ?? "SUB{$sIdx}";
                $subName = $subjectLegend[$subCode]['name'] ?? ($subjectCodes[$sIdx - 1]['code'] ?? "Subject {$sIdx}");
                $fullMark = $subjectFullMarks[$sIdx - 1] ?? '';
                $passMark = $subjectPassMarks[$sIdx - 1] ?? '';

                $ese = isset($row[$colPointer]) ? trim((string)$row[$colPointer]) : '';
                $sie = isset($row[$colPointer + 1]) ? trim((string)$row[$colPointer + 1]) : '';
                $tot = isset($row[$colPointer + 2]) ? trim((string)$row[$colPointer + 2]) : '';
                $credit = isset($row[$colPointer + 3]) ? trim((string)$row[$colPointer + 3]) : '';
                $points = isset($row[$colPointer + 4]) ? trim((string)$row[$colPointer + 4]) : '';
                $grade = isset($row[$colPointer + 5]) ? trim((string)$row[$colPointer + 5]) : '';
                $cp = isset($row[$colPointer + 6]) ? trim((string)$row[$colPointer + 6]) : '';

                // Clean '-' to '0' or empty
                $ese = ($ese === '-' ? '0' : $ese);
                $sie = ($sie === '-' ? '0' : $sie);

                $record["SUB{$sIdx}_CODE"] = $subCode;
                $record["SUB{$sIdx}_NAME"] = $subName;
                $record["SUB{$sIdx}_FULL_MARKS"] = $fullMark;
                $record["SUB{$sIdx}_PASS_MARKS"] = $passMark;
                $record["SUB{$sIdx}_ESE"] = $ese;
                $record["SUB{$sIdx}_SIE"] = $sie;
                $record["SUB{$sIdx}_TOT"] = $tot;
                $record["SUB{$sIdx}_CREDIT"] = $credit;
                $record["SUB{$sIdx}_POINTS"] = $points;
                $record["SUB{$sIdx}_GRADE"] = $grade;
                $record["SUB{$sIdx}_CREDIT_POINTS"] = $cp;

                $colPointer += 7;
            }

            // Summary columns
            $totCreditPoints = isset($row[$colPointer]) ? trim((string)$row[$colPointer]) : '';
            $sgpa = isset($row[$colPointer + 1]) ? trim((string)$row[$colPointer + 1]) : '';
            $grandTotal = isset($row[$colPointer + 2]) ? trim((string)$row[$colPointer + 2]) : '';
            $percentage = isset($row[$colPointer + 3]) ? trim((string)$row[$colPointer + 3]) : '';
            $result = isset($row[$colPointer + 4]) ? trim((string)$row[$colPointer + 4]) : '';

            $record['Total_Credit_Points'] = $totCreditPoints;
            $record['SGPA'] = $sgpa;
            $record['Grand_Total'] = $grandTotal;
            $record['Percentage'] = $percentage;
            $record['Result'] = $result;

            $dataRows[] = $record;
        }

        // Build list of columns
        $allColumns = [];
        if (!empty($dataRows)) {
            $allColumns = array_keys($dataRows[0]);
        }

        return [
            'semester' => $semName,
            'course' => $course,
            'session' => $session,
            'columns' => $allColumns,
            'records' => $dataRows,
            'student_id_col' => 'Roll_No',
            'subject_count' => $numSubjects,
        ];
    }

    /**
     * Extract subject codes and their approximate positions from the header row.
     */
    private function extractSubjectCodes(array $row): array
    {
        $codes = [];
        foreach ($row as $colIdx => $cell) {
            $val = trim((string)$cell);
            if ($val === '' || stripos($val, 'Subject Code') !== false ||
                stripos($val, 'Total Credits') !== false || stripos($val, 'SGPA') !== false ||
                stripos($val, 'Grand Total') !== false || stripos($val, 'Percentage') !== false ||
                stripos($val, 'Result') !== false) {
                continue;
            }

            $codes[] = [
                'col_idx' => $colIdx,
                'code' => $val,
            ];
        }
        return $codes;
    }

    /**
     * Extract non-empty numeric or label values from row.
     */
    private function extractAlignedValues(array $row): array
    {
        $vals = [];
        foreach ($row as $cell) {
            $c = trim((string)$cell);
            if (is_numeric($c)) {
                $vals[] = $c;
            }
        }
        return $vals;
    }

    /**
     * Read all rows as an array of string arrays.
     */
    private function readAllRows(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'])) {
            $handle = fopen($filePath, 'r');
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
            return $rows;
        }

        // Excel
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        return $sheet->toArray(null, true, true, false);
    }
}
