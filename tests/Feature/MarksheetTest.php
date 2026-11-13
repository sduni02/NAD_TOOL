<?php

namespace Tests\Feature;

use App\Models\ReferenceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MarksheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_index_page(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('NAD Tool');
        $response->assertSee('Upload & Store into DB', false);
    }

    public function test_can_upload_csv_and_store_in_db(): void
    {
        $csvContent = "\"id_marksheet\",\"userid\",\"student_id\",\"admission_no\",\"a_course_code\",\"subject_code\",\"semester\",\"subject_credit\",\"total_marks\",\"external_marks\",\"internal_marks\",\"pass_marks\",\"external_marks_obtained\",\"internal_marks_obtained\",\"marks_obtained\",\"grade_obtained\",\"grade_point\",\"credit_point\",\"exam_session\",\"created_at\",\"updated_at\",\"reg\",\"roll\",\"cname\",\"date_of_birth\",\"father_name\",\"mother_name\"\n" .
            "57645,admin,10,\"4007\",C047,COM101,1,5,100,70,30,40,0,0,61,B,7,35,\"2023-2025\",\"2025-09-20 05:42:23\",\"2026-05-05 04:34:56\",\"2405UD19044007\",\"2429044007\",ANKIT KUMAR BARNWAL,\"1992-05-08\",ANIL KUMAR,ANITA DEVI\n" .
            "57646,admin,10,\"4007\",C047,COM102,1,5,100,70,30,40,0,0,68,B,7,35,\"2023-2025\",\"2025-09-20 05:42:23\",\"2026-05-05 04:34:56\",\"2405UD19044007\",\"2429044007\",ANKIT KUMAR BARNWAL,\"1992-05-08\",ANIL KUMAR,ANITA DEVI";

        $file = UploadedFile::fake()->createWithContent('test_marksheet.csv', $csvContent);

        $response = $this->post('/upload', [
            'file' => $file,
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(2, ReferenceRecord::count());

        $first = ReferenceRecord::first();
        $this->assertNotNull($first);
        $this->assertEquals('2429044007', $first->row_data['roll']);
        $this->assertEquals('ANKIT KUMAR BARNWAL', $first->row_data['cname']);
        $this->assertEquals('61', $first->row_data['marks_obtained']);
    }

    public function test_can_upload_excel_and_store_in_db(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['id_marksheet', 'student_id', 'roll', 'reg', 'cname', 'subject_code', 'total_marks', 'marks_obtained', 'grade_obtained'],
            ['99991', '101', '2429099001', 'REG99001', 'RAHUL SHARMA', 'PHY101', '100', '85', 'A'],
            ['99992', '102', '2429099002', 'REG99002', 'PRIYA SINGH', 'CHE101', '100', '92', 'O'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile($tempPath, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->post('/upload', [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(2, ReferenceRecord::count());

        $first = ReferenceRecord::first();
        $this->assertEquals('RAHUL SHARMA', $first->row_data['cname']);
        $this->assertEquals('2429099001', $first->row_data['roll']);
        $this->assertEquals('85', $first->row_data['marks_obtained']);

        @unlink($tempPath);
    }

    public function test_can_upload_file_with_100_columns_and_different_format(): void
    {
        // Generate a 100-column CSV
        $headers = [];
        $headers[] = 'Roll_No';
        $headers[] = 'Student_Full_Name';
        for ($i = 3; $i <= 100; $i++) {
            $headers[] = "Custom_Field_{$i}";
        }

        $row1 = ['ROLL1001', 'VIKAS GUPTA'];
        for ($i = 3; $i <= 100; $i++) {
            $row1[] = "Val_{$i}";
        }

        $csvString = implode(',', $headers) . "\n" . implode(',', $row1) . "\n";
        $file = UploadedFile::fake()->createWithContent('100_columns.csv', $csvString);

        $response = $this->post('/upload', [
            'file' => $file,
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(1, ReferenceRecord::count());

        $columnsJson = DB::table('reference_metadata')->where('key', 'columns')->value('value');
        $storedCols = json_decode($columnsJson, true);
        $this->assertCount(100, $storedCols);

        $rec = ReferenceRecord::first();
        $this->assertEquals('ROLL1001', $rec->student_key);
        $this->assertEquals('VIKAS GUPTA', $rec->row_data['Student_Full_Name']);
        $this->assertEquals('Val_100', $rec->row_data['Custom_Field_100']);
    }

    public function test_can_upload_user_sample_file(): void
    {
        $filePath = 'C:/Users/IT Cell 01/.gemini/antigravity/brain/2eb292bb-d87b-419b-9d29-389544ee522e/.user_uploaded/media_1794576297382_053c1b02.csv';
        if (!file_exists($filePath)) {
            $this->markTestSkipped('Sample file not found.');
        }

        $uploadedFile = new UploadedFile($filePath, 'SEM1_PGECO.csv', 'text/csv', null, true);

        $response = $this->post('/upload', [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(1138, ReferenceRecord::count());

        // Test refresh clears all 1138 rows and metadata
        $refreshResponse = $this->post('/refresh');
        $refreshResponse->assertRedirect('/');
        $this->assertEquals(0, ReferenceRecord::count());
        $this->assertEquals(0, DB::table('reference_metadata')->count());
    }

    public function test_refresh_button_clears_database(): void
    {
        ReferenceRecord::create([
            'student_key' => '12345',
            'row_data' => ['roll' => '12345', 'cname' => 'Test Student'],
        ]);

        $this->assertEquals(1, ReferenceRecord::count());

        $response = $this->post('/refresh');
        $response->assertRedirect('/');
        $this->assertEquals(0, ReferenceRecord::count());
        $this->assertEquals(0, DB::table('reference_metadata')->count());
    }
}
