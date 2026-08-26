<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeacherInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📚 Bienvenido a AulaSync - Activa tu cuenta',
        );
    }

    public function content(): Content
    {
        $invite = $this->invitation->teacherInvite;

        return new Content(
            view: 'emails.teacher-invitation',
            with: [
                'teacherName' => $this->invitation->name ?: ($invite?->display_name ?: 'profesor'),
                'colegioName' => $this->invitation->colegio?->name ?: 'tu colegio',
                'link' => $this->invitation->acceptUrl(),
                'code' => $invite?->invite_code,
                'assignment' => $invite?->subject_name
                    ? trim($invite->subject_name.' '.($invite->grade ?: ''))
                    : null,
                'expiresAt' => $this->invitation->expires_at?->format('d/m/Y'),
            ],
        );
    }
}
