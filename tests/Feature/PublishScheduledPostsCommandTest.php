<?php

namespace Tests\Feature;

use App\Jobs\ProcessPostPublishingJob;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishScheduledPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test command finds past scheduled posts, updates them to published, and dispatches the job.
     */
    public function test_command_publishes_due_scheduled_posts_and_dispatches_job(): void
    {
        Queue::fake();

        // 1. Post ready to publish (scheduled 5 minutes ago)
        $duePost = Post::factory()->create([
            'title'        => 'Due Scheduled Post',
            'status'       => 'scheduled',
            'published_at' => now()->subMinutes(5),
        ]);

        // 2. Future post (should NOT be published yet)
        $futurePost = Post::factory()->create([
            'title'        => 'Future Scheduled Post',
            'status'       => 'scheduled',
            'published_at' => now()->addHour(),
        ]);

        // 3. Draft post (should NOT be published)
        $draftPost = Post::factory()->create([
            'title'        => 'Draft Post',
            'status'       => 'draft',
            'published_at' => null,
        ]);

        // Run the command
        $this->artisan('blog:publish-scheduled')
            ->expectsOutputToContain('Found 1 scheduled post(s) ready to publish.')
            ->assertExitCode(Command::SUCCESS);

        // Assert due post was updated to published
        $this->assertDatabaseHas('posts', [
            'id'     => $duePost->id,
            'status' => 'published',
        ]);

        // Assert future post is STILL scheduled
        $this->assertDatabaseHas('posts', [
            'id'     => $futurePost->id,
            'status' => 'scheduled',
        ]);

        // Assert draft post is STILL draft
        $this->assertDatabaseHas('posts', [
            'id'     => $draftPost->id,
            'status' => 'draft',
        ]);

        // Assert job was dispatched with the due post ID
        Queue::assertPushed(ProcessPostPublishingJob::class, function ($job) use ($duePost, $futurePost) {
            return in_array($duePost->id, $job->postIds)
                && !in_array($futurePost->id, $job->postIds);
        });
    }

    /**
     * Test command exits cleanly when no scheduled posts are due.
     */
    public function test_command_exits_cleanly_when_no_scheduled_posts_due(): void
    {
        Queue::fake();

        // Future post only
        Post::factory()->create([
            'status'       => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $this->artisan('blog:publish-scheduled')
            ->expectsOutputToContain('No scheduled posts to publish right now.')
            ->assertExitCode(Command::SUCCESS);

        Queue::assertNotPushed(ProcessPostPublishingJob::class);
    }
}
