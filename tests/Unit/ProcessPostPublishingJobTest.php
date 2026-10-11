<?php

namespace Tests\Unit;

use App\Exports\PostsExport;
use App\Jobs\ProcessPostPublishingJob;
use App\Mail\PublishedPostsExportMail;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProcessPostPublishingJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the job constructor correctly normalizes all input variations into integer IDs.
     */
    public function test_job_constructor_normalizes_all_input_types(): void
    {
        $post1 = Post::factory()->create(['title' => 'Post One']);
        $post2 = Post::factory()->create(['title' => 'Post Two']);

        // 1. Single Post model
        $jobFromModel = new ProcessPostPublishingJob($post1);
        $this->assertEquals([$post1->id], $jobFromModel->postIds);

        // 2. Single integer ID
        $jobFromInt = new ProcessPostPublishingJob($post1->id);
        $this->assertEquals([$post1->id], $jobFromInt->postIds);

        // 3. Array of IDs
        $jobFromArray = new ProcessPostPublishingJob([$post1->id, $post2->id]);
        $this->assertEquals([$post1->id, $post2->id], $jobFromArray->postIds);

        // 4. Array of Post model objects
        $jobFromModelArray = new ProcessPostPublishingJob([$post1, $post2]);
        $this->assertEquals([$post1->id, $post2->id], $jobFromModelArray->postIds);

        // 5. Eloquent Collection
        $collection = Post::whereIn('id', [$post1->id, $post2->id])->get();
        $jobFromCollection = new ProcessPostPublishingJob($collection);
        $this->assertEquals([$post1->id, $post2->id], $jobFromCollection->postIds);
    }

    /**
     * Test executing the job updates post statuses, exports Excel, and emails to author.
     */
    public function test_job_execution_updates_status_and_exports_excel(): void
    {
        Excel::fake();
        Mail::fake();

        $post1 = Post::factory()->create([
            'title'        => 'Job Test Post 1',
            'content'      => str_repeat('Word ', 400), // 400 words = 2 min read
            'status'       => 'scheduled',
            'published_at' => now()->subMinute(),
        ]);

        $post2 = Post::factory()->create([
            'title'        => 'Job Test Post 2',
            'content'      => str_repeat('Word ', 200), // 200 words = 1 min read
            'status'       => 'scheduled',
            'published_at' => now()->subMinute(),
        ]);

        $job = new ProcessPostPublishingJob([$post1->id, $post2->id]);
        $job->exportFileName = 'exports/published_posts.xlsx';
        $job->handle();

        // 1. Assert post statuses were updated in the database
        $this->assertDatabaseHas('posts', [
            'id'     => $post1->id,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('posts', [
            'id'     => $post2->id,
            'status' => 'published',
        ]);

        // 2. Assert Excel spreadsheet was generated with exact post IDs
        Excel::assertStored('exports/published_posts.xlsx', 'local', function (PostsExport $export) use ($post1, $post2) {
            $collection = $export->collection();
            return $collection->pluck('id')->contains($post1->id)
                && $collection->pluck('id')->contains($post2->id);
        });

        // 3. Assert email was dispatched to isfandiyorazimboyev06@gmail.com with the attachment
        Mail::assertSent(PublishedPostsExportMail::class, function (PublishedPostsExportMail $mail) {
            return $mail->hasTo('isfandiyorazimboyev06@gmail.com')
                && $mail->postCount === 2
                && str_contains($mail->filePath, 'published_posts.xlsx');
        });
    }

    /**
     * Test that PublishedPostsExportMail has valid envelope, htmlString content (no Blade), and attachment.
     */
    public function test_published_posts_export_mail_structure(): void
    {
        $dummyPath = storage_path('app/private/exports/dummy_test.xlsx');
        $mailable = new PublishedPostsExportMail($dummyPath, 7);

        // Envelope
        $this->assertEquals('Your Published Blog Posts Report (7 Posts)', $mailable->envelope()->subject);

        // Content (pure HTML string, no Blade view)
        $content = $mailable->content();
        $this->assertNull($content->view);
        $this->assertStringContainsString('Hello Author', $content->htmlString);
        $this->assertStringContainsString('7', $content->htmlString);

        // Attachments
        $attachments = $mailable->attachments();
        $this->assertCount(1, $attachments);
    }

    /**
     * Test job archives Excel file to cloud disk (DO Spaces / S3) when ARCHIVE_DISK is configured.
     */
    public function test_job_archives_to_cloud_storage_when_configured(): void
    {
        Storage::fake('do_spaces');
        config(['filesystems.cloud_archive_disk' => 'do_spaces']);

        // Create temporary dummy file to simulate generated Excel file
        $tempPath = storage_path('app/private/exports/test_archive.xlsx');
        if (! file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        file_put_contents($tempPath, 'fake-excel-content');

        $job = new ProcessPostPublishingJob([1]);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('archiveToCloud');
        $method->invoke($job, $tempPath, 'exports/test_archive.xlsx');

        Storage::disk('do_spaces')->assertExists('exports/test_archive.xlsx');

        // Cleanup
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
        config(['filesystems.cloud_archive_disk' => null]);
    }

    /**
     * Test job handles empty post ID array gracefully without errors.
     */
    public function test_job_handles_empty_post_ids_cleanly(): void
    {
        $job = new ProcessPostPublishingJob([]);
        $job->handle();

        $this->assertEmpty($job->postIds);
    }
}

