<?php

namespace App\Jobs;

use App\Exports\PostsExport;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessPostPublishingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [5, 15];
    public int $timeout = 300; // 5 minutes timeout for batches

    /**
     * Stored list of Post IDs to process.
     *
     * @var array<int>
     */
    public array $postIds = [];

    /**
     * Create a new job instance.
     * Accepts:
     * - A single Post model: ProcessPostPublishingJob::dispatch($post)
     * - A single ID:         ProcessPostPublishingJob::dispatch(1)
     * - An array of IDs:     ProcessPostPublishingJob::dispatch([1, 2, 3])
     * - An Eloquent/Support collection of posts
     */
    public function __construct(Post|array|int|Enumerable|null $posts = null)
    {
        $this->onQueue('blog_posts_queue');

        if ($posts instanceof Post) {
            $this->postIds = [$posts->id];
        } elseif (is_int($posts)) {
            $this->postIds = [$posts];
        } elseif ($posts instanceof Enumerable) {
            $this->postIds = $posts->pluck('id')->map(fn ($id) => (int) $id)->all();
        } elseif (is_array($posts)) {
            $this->postIds = array_map(fn ($p) => $p instanceof Post ? (int) $p->id : (int) $p, array_values($posts));
        } else {
            $this->postIds = [];
        }
    }

    /**
     * Execute the job in the background.
     */
    public function handle(): void
    {
        $total = count($this->postIds);

        if ($total === 0) {
            Log::info(" [Job Skipped] No post IDs provided for processing.");
            return;
        }

        Log::info(" [Job Started] Processing {$total} post(s) in background...");

        $successCount = 0;
        $failCount = 0;

        // 1. MEMORY-SAFE CHUNKING IN PHP:
        // Slice the ID array in memory (0 SQL queries, 0 microsecond cost).
        // Avoids Post::whereIn()->chunk() which causes slow LIMIT/OFFSET queries.
        $chunks = array_chunk($this->postIds, 100);

        foreach ($chunks as $chunkIndex => $chunkIds) {
            $this->processChunk($chunkIds, $successCount, $failCount);
        }

        // 2. EXCEL EXPORT (Runs ONCE after all posts are processed):
        // Generates ONE unified .xlsx file containing all posts in this batch.
        $this->exportToExcel();

        Log::info(" [Job Completed] Finished processing {$total} post(s). Succeeded: {$successCount}, Failed: {$failCount}.");
    }

    /**
     * Process a single batch chunk (100 posts at a time).
     *
     * @param array<int> $chunkIds
     */
    protected function processChunk(array $chunkIds, int &$successCount, int &$failCount): void
    {
        // One clean SQL query for exactly this 100-item chunk:
        // SELECT * FROM posts WHERE id IN (...)
        $posts = Post::whereIn('id', $chunkIds)->get();

        foreach ($posts as $post) {
            $this->processSinglePost($post, $successCount, $failCount);
        }
    }

    /**
     * Process business logic for an individual post with isolated error handling.
     */
    protected function processSinglePost(Post $post, int &$successCount, int &$failCount): void
    {
        try {
            // Ensure status is published and published_at is populated in database:
            if ($post->status !== 'published' || empty($post->published_at)) {
                $post->update([
                    'status' => 'published',
                    'published_at' => $post->published_at ?? now(),
                ]);
            }

            // Business Logic: Calculate read time & log
            $wordCount = str_word_count($post->content);
            $minutesToRead = ceil($wordCount / 200);

            Log::info("  ✓ Processed & Published Post #{$post->id}: '{$post->title}' ({$minutesToRead} min read).");
            $successCount++;

        } catch (Throwable $e) {
            // Isolate failure: corrupt data on 1 post never crashes the rest of the batch!
            Log::error("  ✗ Error processing Post #{$post->id}: " . $e->getMessage());
            $failCount++;
        }
    }

    /**
     * Export all published posts into ONE master Excel (.xlsx) file in storage.
     * Newly published posts are included alongside previous ones so no data is lost!
     */
    protected function exportToExcel(): void
    {
        try {
            $fileName = 'exports/published_posts.xlsx';

            // Master spreadsheet: contains ALL published posts in the system
            Excel::store(new PostsExport(), $fileName, 'local');

            $totalPublished = Post::where('status', 'published')->count();
            Log::info(" [Excel Export] Successfully updated master spreadsheet with all {$totalPublished} published post(s): storage/app/private/{$fileName}");
        } catch (Throwable $e) {
            // Excel failure does not revert post publications, but logs alert for administrators
            Log::error(" [Excel Export Failed] " . $e->getMessage());
        }
    }

    /**
     * Handle catastrophic job failure after all retries are exhausted (RabbitMQ DLQ).
     */
    public function failed(?Throwable $exception): void
    {
        Log::error(" [Job Failed] Batch processing failed permanently. Reason: " . $exception?->getMessage());
    }
}

