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
        public int $postCount = 0
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Published Blog Posts Report ({$this->postCount} post(s))",
        );
    }

    /**
     * Get the message content definition (clean raw text, no blade template required).
     */
    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e(
                "Hello,\n\n"
                . "The background job has successfully processed and published blog posts.\n"
                . "Total posts in this batch: {$this->postCount}\n\n"
                . "Author: isfandiyorazimboyev06-glitch\n"
                . "System: BlogAPI\n\n"
                . "Please find the attached Excel spreadsheet report."
            ))
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! file_exists($this->filePath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->filePath)
                ->as('published_posts.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
