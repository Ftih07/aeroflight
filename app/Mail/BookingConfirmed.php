<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public $booking, public $child_booking, public $pdfContent) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Confirmed - AeroFlight',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn() => $this->pdfContent, 'AeroFlight_Ticket_' . $this->booking->pnr_code . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
