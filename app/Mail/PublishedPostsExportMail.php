<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublishedPostsExportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $filePath,
        public int $postCount
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Published Blog Posts Report ({$this->postCount} Posts)",
        );
    }

    /**
     * Get the message content definition (No Blade file needed!).
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h2 style='color: #2c3e50;'>Hello Author,</h2>
                    <p>Your blog posts have been successfully published by the background worker.</p>
                    <p>Total posts in this report: <strong>{$this->postCount}</strong></p>
                    <p>The detailed Excel spreadsheet has been attached to this email for your records.</p>
                    <br>
                    <p>Best regards,<br><strong>Blog API Publishing System</strong></p>
                </div>
            "
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as('published_posts.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
