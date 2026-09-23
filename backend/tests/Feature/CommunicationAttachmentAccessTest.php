<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\CommunicationAnnouncement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchoolAndUsers(): array
    {
        $colegio = Colegio::create(['name' => 'Colegio Test', 'invite_code' => 'TST-1']);

        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $director = User::factory()->create([
            'role' => 'director',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $parent = User::factory()->create([
            'role' => 'representante',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
            'family_code' => 'FAM-1',
        ]);

        $student = Student::create([
            'teacher_id' => $teacher->id,
            'name' => 'Alumno Prueba',
            'grade' => '3ro',
            'section' => 'A',
            'colegio_id' => $colegio->id,
            'family_code' => 'FAM-1',
        ]);

        return [$colegio, $teacher, $director, $parent, $student];
    }

    public function test_unauthenticated_cannot_download_attachment()
    {
        Storage::fake('local');
        [$colegio, $teacher] = $this->makeSchoolAndUsers();

        $file = UploadedFile::fake()->create('adj.pdf', 120);
        $path = $file->store('communication-attachments/'.$teacher->id, 'local');

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'adj.pdf', 'path' => $path, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 0]);
        $resp = $this->getJson($url);
        $resp->assertStatus(401);
    }

    public function test_teacher_same_school_can_download()
    {
        Storage::fake('local');
        [$colegio, $teacher] = $this->makeSchoolAndUsers();

        $file = UploadedFile::fake()->create('adj.pdf', 120);
        $path = $file->store('communication-attachments/'.$teacher->id, 'local');

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'adj.pdf', 'path' => $path, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 0]);
        $resp = $this->actingAs($teacher)->get($url);
        $resp->assertStatus(200);
        $this->assertStringContainsString('attachment', $resp->headers->get('content-disposition'));
    }

    public function test_teacher_other_school_blocked()
    {
        Storage::fake('local');
        [$colegio, $teacher, , ] = $this->makeSchoolAndUsers();
        $otherColegio = Colegio::create(['name' => 'Otro', 'invite_code' => 'OTR']);
        $otherTeacher = User::factory()->create(['role' => 'profesor', 'colegio_id' => $otherColegio->id, 'onboarding_completed' => true]);

        $file = UploadedFile::fake()->create('adj.pdf', 120);
        $path = $file->store('communication-attachments/'.$teacher->id, 'local');

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'adj.pdf', 'path' => $path, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 0]);
        $resp = $this->actingAs($otherTeacher)->getJson($url);
        $resp->assertStatus(403);
    }

    public function test_director_same_school_can_download()
    {
        Storage::fake('local');
        [$colegio, $teacher, $director] = $this->makeSchoolAndUsers();

        $file = UploadedFile::fake()->create('adj.pdf', 120);
        $path = $file->store('communication-attachments/'.$teacher->id, 'local');

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'adj.pdf', 'path' => $path, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 0]);
        $resp = $this->actingAs($director)->get($url);
        $resp->assertStatus(200);
    }

    public function test_director_other_school_blocked()
    {
        Storage::fake('local');
        [$colegio, $teacher, $director] = $this->makeSchoolAndUsers();
        $otherColegio = Colegio::create(['name' => 'Otro', 'invite_code' => 'OTR']);
        $otherDirector = User::factory()->create(['role' => 'director', 'colegio_id' => $otherColegio->id, 'onboarding_completed' => true]);

        $file = UploadedFile::fake()->create('adj.pdf', 120);
        $path = $file->store('communication-attachments/'.$teacher->id, 'local');

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'adj.pdf', 'path' => $path, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 0]);
        $resp = $this->actingAs($otherDirector)->getJson($url);
        $resp->assertStatus(403);
    }

    public function test_representative_authorized_and_unauthorized()
    {
        Storage::fake('local');
        [$colegio, $teacher, $director, $parent, $student] = $this->makeSchoolAndUsers();
        $otherParent = User::factory()->create(['role' => 'representante', 'colegio_id' => $colegio->id, 'onboarding_completed' => true, 'family_code' => 'FAM-OTHER']);

        $file = UploadedFile::fake()->create('adj.pdf', 120);
        $path = $file->store('communication-attachments/'.$teacher->id, 'local');

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'targeting' => ['course_id' => null],
            'attachments' => [
                ['type' => 'file', 'name' => 'adj.pdf', 'path' => $path, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 0]);
        // authorized parent (passes estudiante_id param)
        $resp = $this->actingAs($parent)->getJson($url.'?estudiante_id='.$student->id);
        $resp->assertStatus(200);

        // unauthorized parent
        $resp2 = $this->actingAs($otherParent)->getJson($url.'?estudiante_id='.$student->id);
        $resp2->assertStatus(403);
    }

    public function test_nonexistent_attachment_404_and_path_traversal_blocked()
    {
        Storage::fake('local');
        [$colegio, $teacher, $director, $parent, $student] = $this->makeSchoolAndUsers();

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'good.pdf', 'path' => 'communication-attachments/'.$teacher->id.'/good.pdf', 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 120],
            ],
            'status' => 'sent',
        ]);

        $url = route('communication.attachment.download', ['announcement' => $announcement->id, 'idx' => 5]);
        $this->actingAs($teacher)->getJson($url)->assertStatus(404);

        // path traversal attempt in DB record
        $announcement2 = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => 'Mal',
            'body' => 'Cuerpo',
            'attachments' => [
                ['type' => 'file', 'name' => 'bad', 'path' => '../.env', 'disk' => 'local', 'mime' => 'text/plain', 'size' => 10],
            ],
            'status' => 'sent',
        ]);
        $url2 = route('communication.attachment.download', ['announcement' => $announcement2->id, 'idx' => 0]);
        $this->actingAs($teacher)->getJson($url2)->assertStatus(403);
    }
}

