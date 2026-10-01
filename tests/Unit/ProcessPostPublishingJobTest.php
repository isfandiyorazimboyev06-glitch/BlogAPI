<?php

namespace Tests\Unit;

use App\Exports\PostsExport;
use App\Jobs\ProcessPostPublishingJob;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * Test executing the job updates post statuses and exports the Excel file.
     */
    public function test_job_execution_updates_status_and_exports_excel(): void
    {
        Excel::fake();

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
        $job->handle();

        // Assert posts statuses were updated to published
        $this->assertDatabaseHas('posts', [
            'id'     => $post1->id,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('posts', [
            'id'     => $post2->id,
            'status' => 'published',
        ]);

        // Assert master Excel spreadsheet was saved
        Excel::assertStored('exports/published_posts.xlsx', 'local', function (PostsExport $export) use ($post1, $post2) {
            $collection = $export->collection();
            return $collection->pluck('id')->contains($post1->id)
                && $collection->pluck('id')->contains($post2->id);
        });
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
