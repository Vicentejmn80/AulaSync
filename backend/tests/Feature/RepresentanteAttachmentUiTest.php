<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepresentanteAttachmentUiTest extends TestCase
{
    use RefreshDatabase;

    private function baseViewData(array $announcements = [])
    {
        return [
            'students' => collect([]),
            'reasons' => collect([]),
            'calendar' => ['month' => now()->format('Y-m'), 'events' => new \stdClass()],
            'summary' => [],
            'subjects' => collect([]),
            'announcements' => $announcements,
            'threads' => collect([]),
            'notifications' => [],
            'schoolName' => null,
            'parent' => ['name' => 'Test Parent', 'email' => 'p@test.test', 'initials' => 'T'],
        ];
    }

    public function test_private_file_attachment_uses_download_url()
    {
        $announcements = [
            [
                'id' => 1,
                'title' => 'A',
                'body' => 'B',
                'attachments' => [
                    ['type' => 'file', 'name' => 'doc.pdf', 'path' => 'communication-attachments/1/doc.pdf', 'disk' => 'local', 'download_url' => '/communication/attachments/1/0'],
                ],
                'official' => true,
            ],
        ];

        $html = view('representante.hub', $this->baseViewData($announcements))->render();
        $this->assertStringContainsString('/communication/attachments/1/0', $html);
        $this->assertStringNotContainsString('Adjunto pendiente de migración', $html);
    }

    public function test_legacy_file_attachment_shows_pending_and_no_public_url()
    {
        $announcements = [
            [
                'id' => 2,
                'title' => 'Legacy',
                'body' => 'Legacy body',
                'attachments' => [
                    ['type' => 'file', 'name' => 'old.pdf', 'path' => '/storage/communication-attachments/old.pdf', 'url' => '/storage/communication-attachments/old.pdf', 'disk' => null, 'download_url' => null],
                ],
                'official' => true,
            ],
        ];

        $html = view('representante.hub', $this->baseViewData($announcements))->render();
        $this->assertStringContainsString('Adjunto pendiente de migración', $html);
        $this->assertStringNotContainsString('/storage/communication-attachments/old.pdf', $html);
    }

    public function test_drive_attachment_uses_external_url()
    {
        $announcements = [
            [
                'id' => 3,
                'title' => 'Drive',
                'body' => 'Drive body',
                'attachments' => [
                    ['type' => 'drive', 'name' => 'Drive link', 'url' => 'https://drive.google.com/file/d/XYZ'],
                ],
                'official' => true,
            ],
        ];

        $html = view('representante.hub', $this->baseViewData($announcements))->render();
        $this->assertStringContainsString('https://drive.google.com/file/d/XYZ', $html);
    }
}

