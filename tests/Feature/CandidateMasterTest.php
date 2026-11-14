<?php

namespace Tests\Feature;

use App\Models\ReferenceRecord;
use App\Models\CandidateMasterRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateMasterTest extends TestCase
{
    use RefreshDatabase;

    private function seedReferenceData(): void
    {
        $cols = ['Roll_No', 'Reg_No', 'Student_Name', 'SUB1_CODE', 'SUB1_TOT', 'SGPA', 'Result'];
        DB::table('reference_metadata')->insert([
            ['key' => 'columns', 'value' => json_encode($cols), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'student_id_column', 'value' => 'Roll_No', 'created_at' => now(), 'updated_at' => now()],
        ]);

        ReferenceRecord::create([
            'student_key' => '24290413276',
            'row_data' => [
                'Roll_No' => '24290413276',
                'Reg_No' => '2405UD190413276',
                'Student_Name' => 'Abhimanyu Kumar',
                'SUB1_CODE' => 'AEC-1',
                'SUB1_TOT' => '31',
                'SGPA' => '7.8',
                'Result' => 'PASS',
            ],
        ]);
    }

    public function test_can_upload_candidate_master_with_apaar_and_aadhaar(): void
    {
        $csvContent = "Roll_No,Candidate_Name,APAAR_ID,AADHAAR_NO\n" .
                      "24290413276,Abhimanyu Kumar,ABC123456789,987654321012\n" .
                      "24290413729,ABHISHEK KUMAR,ABC987654321,123456789012\n";

        $file = UploadedFile::fake()->createWithContent('candidates_apaar.csv', $csvContent);

        $response = $this->post('/upload-candidate-master', [
            'master_file' => $file,
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(2, CandidateMasterRecord::count());

        $first = CandidateMasterRecord::first();
        $this->assertEquals('24290413276', $first->match_key);
        $this->assertEquals('ABC123456789', $first->row_data['APAAR_ID']);
        $this->assertEquals('987654321012', $first->row_data['AADHAAR_NO']);
    }

    public function test_mapping_workspace_populates_apaar_and_aadhaar_from_second_file(): void
    {
        $this->seedReferenceData();

        // Seed candidate master
        CandidateMasterRecord::create([
            'match_key' => '24290413276',
            'row_data' => [
                'Roll_No' => '24290413276',
                'APAAR_ID' => '112233445566',
                'AADHAAR_NO' => '998877665544',
            ],
        ]);

        DB::table('candidate_master_metadata')->insert([
            ['key' => 'columns', 'value' => json_encode(['Roll_No', 'APAAR_ID', 'AADHAAR_NO']), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'match_key_column', 'value' => 'Roll_No', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $mapping = [
            'RROLL' => 'ref:Roll_No',
            'CNAME' => 'ref:Student_Name',
            'ABC_ACCOUNT_ID' => 'master:APAAR_ID',
            'AADHAAR_NAME' => 'master:AADHAAR_NO',
        ];

        $response = $this->post('/mapping/preview', [
            'mapping' => $mapping,
            'static_values' => [],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['rows']);
        $headers = $data['headers'];
        $row = $data['rows'][0];

        $rollIdx = array_search('RROLL', $headers);
        $apaarIdx = array_search('ABC_ACCOUNT_ID', $headers);
        $aadhaarIdx = array_search('AADHAAR_NAME', $headers);

        $this->assertEquals('24290413276', $row[$rollIdx]);
        $this->assertEquals('112233445566', $row[$apaarIdx]); // APAAR ID mapped from 2nd file!
        $this->assertEquals('998877665544', $row[$aadhaarIdx]); // Aadhaar mapped from 2nd file!
    }

    public function test_can_clear_candidate_master(): void
    {
        CandidateMasterRecord::create([
            'match_key' => '12345',
            'row_data' => ['Roll_No' => '12345', 'APAAR_ID' => '99999'],
        ]);

        $this->assertEquals(1, CandidateMasterRecord::count());

        $response = $this->post('/clear-candidate-master');
        $response->assertRedirect('/');

        $this->assertEquals(0, CandidateMasterRecord::count());
    }
}
