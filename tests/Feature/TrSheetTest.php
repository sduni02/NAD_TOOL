<?php

namespace Tests\Feature;

use App\Models\ReferenceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrSheetTest extends TestCase
{
    use RefreshDatabase;

    private function getSampleTrContent(): string
    {
        return <<<'EOD'
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,"                                                                                                               Ghatsila, East Singhbhum-Jharkhand",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NAME OF SCHOOL:- SCHOOL OF SCIENCE,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Tabulated Result (T.R),,,,,,,,,
,COURSE: ,B.S.C. MATHEMATICS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Session- 2023-27,,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Semester-I (Regular),,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Exam: ………………..,,,,,,,,,
,,,Subject Code,AEC-1,,,,,,,VAC-1,,,,,,,SEC-1 ,,,,,,,MDC-1,,,,,,,MN-1A,,,,,,,MJ-1,,,,,,,Total Credits Points,SGPA,Grand Total              (Out of 500),Percentage,Result
,,,Full marks,50,,,,,,,100,,,,,,,75,,,,,,,75,,,,,,,100,,,,,,,100,,,,,,,,,,,
,,,Pass Marks,20,,,,,,,40,,,,,,,30,,,,,,,30,,,,,,,40,,,,,,,40,,,,,,,,,,,
S.N,Reg. No,University Roll No,Student Name,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,,,,,
,,,,50,-,50,,,,,50,50,100,,,,,75,-,75,,,,,75,-,75,,,,,75,25,100,,,,,75,25,100,,,,,,,,,
9279-01,2405UD190413276,24290413276,Abhimanyu Kumar,31,-,31,2,8,A,16,32,25,57,4,7,B+,28,45,-,45,3,8,A,24,46,-,46,3,8,A,24,50,19,69,4,8,A,32,46,17,63,4,8,A,32,156,7.8,311,62.2,PASS
9732-01,2405UD190413729,24290413729,ABHISHEK KUMAR,30,-,30,2,8,A,16,30,22,52,4,6,B,24,46,-,46,3,8,A,24,48,-,48,3,8,A,24,52,17,69,4,8,A,32,48,19,67,4,8,A,32,152,7.6,312,62.4,PASS
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,SUBJETCT CODE,SUBJECT NAME,Cr,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,AEC-1,MIL-HINDI COMMUNICATION,2,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,VAC-1,"UNDERSTANDING INDIA,YOGA EDUCATION & SPORTS",4,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,SEC-1,"THEORY OF SETS, NUMBERS & EQUATIONS",3,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,MDC-1F,MATHEMATICAL & COMPUTATIONAL THINKING & ANALYSIS,3,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,MN-1A,INTRODUCTORY CHEMISTRY,4,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,MJ-1,GEOMETRY & CALCULAS,4,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,,,20,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,"                                                                                                               Ghatsila, East Singhbhum-Jharkhand",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,
,NAME OF SCHOOL:- SCHOOL OF SCIENCE,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Tabulated Result (T.R),,,,,,,,,
,COURSE: ,B.S.C. MATHEMATICS,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Session- 2023-27,,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Semester-II (Regular),,,,,,,,,
,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,Exam: ………………..,,,,,,,,,
,,,Subject Code,AEC-2,,,,,,,SEC-2,,,,,,,MDC-2,,,,,,,MN-2A,,,,,,,MJ-2,,,,,,,MJ-3,,,,,,,Total Credits Points,SGPA,Grand Total              (Out of 500),Percentage,Result
,,,Full marks,50,,,,,,,75,,,,,,,75,,,,,,,100,,,,,,,100,,,,,,,100,,,,,,,,,,,
,,,Pass Marks,20,,,,,,,30,,,,,,,30,,,,,,,40,,,,,,,40,,,,,,,40,,,,,,,,,,,
S.N,Reg. No,University Roll No,Student Name,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,ESE,SIE,Total,Credit,Points,Grade,Credit Points,,,,,
,,,,50,-,50,,,,,75,-,75,,,,,75,-,75,,,,,75,25,100,,,,,75,25,100,,,,,75,25,100,,,,,,,,,
9279-01,2405UD190413276,24290413276,Abhimanyu Kumar,34,-,34,2,8,A,16,48,-,48,3,8,A,24,48,-,48,3,8,A,24,45,16,61,4,8,A,32,40,19,59,4,7,B+,28,46,19,65,4,8,A,32,156,7.8,315,63,PASS
EOD;
    }

    public function test_can_upload_and_parse_tr_sheet(): void
    {
        $file = UploadedFile::fake()->createWithContent('tabulated_result.csv', $this->getSampleTrContent());

        $response = $this->post('/upload', [
            'file' => $file,
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(2, ReferenceRecord::count());

        $first = ReferenceRecord::first();
        $this->assertNotNull($first);
        $this->assertEquals('24290413276', $first->row_data['Roll_No']);
        $this->assertEquals('Abhimanyu Kumar', $first->row_data['Student_Name']);
        $this->assertEquals('AEC-1', $first->row_data['SUB1_CODE']);
        $this->assertEquals('MIL-HINDI COMMUNICATION', $first->row_data['SUB1_NAME']);
        $this->assertEquals('31', $first->row_data['SUB1_TOT']);
        $this->assertEquals('7.8', $first->row_data['SGPA']);
        $this->assertEquals('PASS', $first->row_data['Result']);

        // Verify metadata
        $isTr = DB::table('reference_metadata')->where('key', 'is_tr_sheet')->value('value');
        $this->assertEquals('1', $isTr);

        $activeSem = DB::table('reference_metadata')->where('key', 'active_tr_semester')->value('value');
        $this->assertStringContainsString('Semester-I', $activeSem);
    }

    public function test_can_switch_tr_semester(): void
    {
        $file = UploadedFile::fake()->createWithContent('tabulated_result.csv', $this->getSampleTrContent());
        $this->post('/upload', ['file' => $file]);

        $this->assertEquals(2, ReferenceRecord::count());

        // Switch to Semester-II
        $response = $this->post('/switch-tr-semester', [
            'semester' => 'Semester-II (Regular)',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(1, ReferenceRecord::count());

        $first = ReferenceRecord::first();
        $this->assertEquals('24290413276', $first->row_data['Roll_No']);
        $this->assertEquals('AEC-2', $first->row_data['SUB1_CODE']);
        $this->assertEquals('315', $first->row_data['Grand_Total']);
    }
}
