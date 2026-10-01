<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPostPublishingJob;
use App\Models\Post;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'blog:publish-scheduled';
    protected $description = 'Finds scheduled posts whose published_at time has passed and publishes them.';

    public function handle(): int
    {
        $this->info('Checking for scheduled posts ready to be published...');

        try {
            // 1. Query: Find due post IDs (fast pluck, no heavy model hydration)
            $duePostIds = Post::where('status', 'scheduled')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->limit(1000)
                ->pluck('id')
                ->toArray();

            // 2. If no posts are ready, exit cleanly
            if (empty($duePostIds)) {
                $this->comment("No scheduled posts to publish right now.");
                return Command::SUCCESS;
            }

            $count = count($duePostIds);
            $this->info("Found {$count} scheduled post(s) ready to publish.");

            // 3. ATOMIC TRANSACTION:
            // Update statuses and dispatch background job together.
            // If RabbitMQ dispatch fails, the DB update rolls back so posts aren't lost!
            DB::transaction(function () use ($duePostIds) {
                // Bulk update in 1 single SQL query
                Post::whereIn('id', $duePostIds)->update(['status' => 'published']);

                // Dispatch single background job with all IDs to RabbitMQ
                ProcessPostPublishingJob::dispatch($duePostIds);
            });

            $this->info("Successfully published and dispatched background job for {$count} post(s)! ");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("An error occurred while publishing scheduled posts: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
