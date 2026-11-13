<?php

namespace Tests\Feature;

use App\Models\ReferenceRecord;
use App\Models\MappingPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MappingTest extends TestCase
{
    use RefreshDatabase;

    private function seedSampleData(): void
    {
        $cols = ['roll', 'reg', 'cname', 'date_of_birth', 'father_name', 'mother_name', 'exam_session', 'semester', 'subject_code', 'total_marks', 'marks_obtained', 'grade_obtained', 'grade_point', 'subject_credit', 'credit_point'];
        DB::table('reference_metadata')->insert([
            ['key' => 'columns', 'value' => json_encode($cols), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'student_id_column', 'value' => 'roll', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Student 1 with 2 subjects
        ReferenceRecord::create([
            'student_key' => '2429001',
            'row_data' => [
                'roll' => '2429001',
                'reg' => 'REG001',
                'cname' => 'ANIL SHARMA',
                'date_of_birth' => '2001-01-15',
                'father_name' => 'RAM SHARMA',
                'mother_name' => 'SITA SHARMA',
                'exam_session' => '2023-2025',
                'semester' => '1',
                'subject_code' => 'COM101',
                'total_marks' => '100',
                'marks_obtained' => '70',
                'grade_obtained' => 'A',
                'grade_point' => '8',
                'subject_credit' => '5',
                'credit_point' => '40',
            ],
        ]);

        ReferenceRecord::create([
            'student_key' => '2429001',
            'row_data' => [
                'roll' => '2429001',
                'reg' => 'REG001',
                'cname' => 'ANIL SHARMA',
                'date_of_birth' => '2001-01-15',
                'father_name' => 'RAM SHARMA',
                'mother_name' => 'SITA SHARMA',
                'exam_session' => '2023-2025',
                'semester' => '1',
                'subject_code' => 'COM102',
                'total_marks' => '100',
                'marks_obtained' => '80',
                'grade_obtained' => 'O',
                'grade_point' => '10',
                'subject_credit' => '5',
                'credit_point' => '50',
            ],
        ]);
    }

    public function test_mapping_workspace_loads(): void
    {
        $this->seedSampleData();

        $response = $this->get('/mapping');
        $response->assertStatus(200);
        $response->assertSee('Target Format Mapping Workspace');
        $response->assertSee('REGN_NO');
        $response->assertSee('RROLL');
        $response->assertSee('SUB1');
    }

    public function test_preview_flattens_student_into_one_row(): void
    {
        $this->seedSampleData();

        $mapping = [
            'RROLL' => 'ref:roll',
            'REGN_NO' => 'ref:reg',
            'CNAME' => 'ref:cname',
            'TOT_MRKS' => 'agg_tot_marks',
            'TOT' => 'agg_marks_obtained',
            'TOT_CREDIT' => 'agg_total_credits',
            'SGPA' => 'agg_sgpa',
            'RESULT' => 'agg_result',
            'SUB1' => 'sub_1:subject_code',
            'SUB1_TOT' => 'sub_1:marks_obtained',
            'SUB1_GRADE' => 'sub_1:grade_obtained',
            'ORG_NAME' => 'static',
        ];

        $statics = [
            'ORG_NAME' => 'MY TEST UNIVERSITY',
        ];

        $response = $this->post('/mapping/preview', [
            'mapping' => $mapping,
            'static_values' => $statics,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('rows', $data);
        $this->assertCount(1, $data['rows']);

        $headers = $data['headers'];
        $row = $data['rows'][0];

        $rollIndex = array_search('RROLL', $headers);
        $cnameIndex = array_search('CNAME', $headers);
        $totMarksIndex = array_search('TOT_MRKS', $headers);
        $totObtIndex = array_search('TOT', $headers);
        $sgpaIndex = array_search('SGPA', $headers);
        $resultIndex = array_search('RESULT', $headers);
        $sub1Index = array_search('SUB1', $headers);
        $sub1TotIndex = array_search('SUB1_TOT', $headers);
        $orgIndex = array_search('ORG_NAME', $headers);

        $this->assertEquals('2429001', $row[$rollIndex]);
        $this->assertEquals('ANIL SHARMA', $row[$cnameIndex]);
        $this->assertEquals('200', $row[$totMarksIndex]);
        $this->assertEquals('150', $row[$totObtIndex]);
        $this->assertEquals('9.00', $row[$sgpaIndex]);
        $this->assertEquals('PASS', $row[$resultIndex]);
        $this->assertEquals('COM101', $row[$sub1Index]);
        $this->assertEquals('70', $row[$sub1TotIndex]);
        $this->assertEquals('MY TEST UNIVERSITY', $row[$orgIndex]);
    }

    public function test_can_export_csv(): void
    {
        $this->seedSampleData();

        $response = $this->post('/mapping/export', [
            'export_format' => 'csv',
            'mapping' => [
                'RROLL' => 'ref:roll',
                'CNAME' => 'ref:cname',
            ],
            'static_values' => [],
        ]);

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), '.csv'));
    }

    public function test_can_upload_custom_template(): void
    {
        $customCsv = "STUDENT_ROLL,STUDENT_NAME,SUB_1,SUB_2,FINAL_MARKS\n";
        $file = UploadedFile::fake()->createWithContent('custom_nad.csv', $customCsv);

        $response = $this->post('/mapping/upload-template', [
            'template_file' => $file,
        ]);

        $response->assertRedirect('/mapping');
        $this->assertEquals(['STUDENT_ROLL', 'STUDENT_NAME', 'SUB_1', 'SUB_2', 'FINAL_MARKS'], session('target_headers'));
    }

    public function test_can_save_preset(): void
    {
        $response = $this->post('/mapping/save-preset', [
            'preset_name' => 'Standard College Preset',
            'mapping' => ['RROLL' => 'ref:roll', 'CNAME' => 'ref:cname'],
            'static_values' => ['ORG_NAME' => 'XYZ College'],
        ]);

        $this->assertDatabaseHas('mapping_presets', [
            'name' => 'Standard College Preset',
        ]);
    }
}
