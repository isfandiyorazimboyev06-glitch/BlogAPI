<?php

namespace Tests\Feature;

use App\Jobs\ProcessPostPublishingJob;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test retrieving a paginated list of posts.
     */
    public function test_can_list_all_posts(): void
    {
        Post::factory()->count(5)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'content',
                        'status',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test retrieving a single post by ID.
     */
    public function test_can_get_single_post(): void
    {
        $post = Post::factory()->create([
            'title' => 'Sample Single Post',
            'content' => 'Sample content here.',
        ]);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id'    => $post->id,
                    'title' => 'Sample Single Post',
                ],
            ]);
    }

    /**
     * Test creating a draft post does NOT dispatch a background job.
     */
    public function test_creating_draft_post_does_not_dispatch_job(): void
    {
        Queue::fake();

        $payload = [
            'title'   => 'Draft Post',
            'content' => 'Draft content.',
            'status'  => 'draft',
        ];

        $response = $this->postJson('/api/posts', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('posts', [
            'title'  => 'Draft Post',
            'status' => 'draft',
        ]);

        Queue::assertNotPushed(ProcessPostPublishingJob::class);
    }

    /**
     * Test creating an immediately published post dispatches background job and sets published_at timestamp.
     */
    public function test_creating_published_post_dispatches_job_and_sets_timestamp(): void
    {
        Queue::fake();

        $payload = [
            'title'   => 'Immediate Published Post',
            'content' => 'Will publish now.',
            'status'  => 'published',
        ];

        $response = $this->postJson('/api/posts', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('posts', [
            'title'  => 'Immediate Published Post',
            'status' => 'published',
        ]);

        $post = Post::first();
        $this->assertNotNull($post->published_at);

        Queue::assertPushed(ProcessPostPublishingJob::class, function ($job) use ($post) {
            return in_array($post->id, $job->postIds);
        });
    }

    /**
     * Test that scheduled posts require a published_at timestamp.
     */
    public function test_scheduled_post_requires_published_at_timestamp(): void
    {
        $payload = [
            'title'   => 'Invalid Scheduled Post',
            'content' => 'Missing timestamp.',
            'status'  => 'scheduled',
            // published_at omitted!
        ];

        $response = $this->postJson('/api/posts', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['published_at']);
    }

    /**
     * Test updating a draft post to published triggers the background job.
     */
    public function test_updating_post_to_published_dispatches_job(): void
    {
        Queue::fake();

        $post = Post::factory()->draft()->create();

        $updatePayload = [
            'status' => 'published',
        ];

        $response = $this->putJson("/api/posts/{$post->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => 'published',
        ]);

        Queue::assertPushed(ProcessPostPublishingJob::class, function ($job) use ($post) {
            return in_array($post->id, $job->postIds);
        });
    }

    /**
     * Test deleting a post by ID.
     */
    public function test_can_delete_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Post deleted successfully.',
            ]);

        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }
}
