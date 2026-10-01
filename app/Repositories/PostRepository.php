<?php

namespace App\Repositories;

use App\Http\Resources\PostResource;
use App\Interfaces\PostRepositoryInterface;
use App\Jobs\ProcessPostPublishingJob;
use App\Models\Post;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;



class PostRepository implements PostRepositoryInterface
{
    /**
     * Get all posts, paginated, newest first.
     */
    public function getAll(int $perPage=10):AnonymousResourceCollection
    {
        $posts = Post::latest()->paginate($perPage);

        return PostResource::collection($posts);
    }

    /**
     * Find a post by ID or throw 404.
     */
    public function getById(int $id): PostResource
    {
        $post = Post::findOrFail($id);

        return new PostResource($post);
    }

    /**
     * Create a new post in the database.
     */
    public function create(array $data): PostResource
    {
        return DB::transaction(function () use ($data) {
            // If post is published immediately, ensure published_at timestamp is set
            if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
                $data['published_at'] = now();
            }

            $post = Post::create($data);

            // Only notify subscribers / process if published immediately
            if ($post->status === 'published') {
                ProcessPostPublishingJob::dispatch($post);
            }

            return new PostResource($post);
        });
    }

    /**
     * Update a post by ID.
     */
    public function update(int $id, array $data): PostResource
    {
        return DB::transaction(function () use ($id, $data) {
            $post = Post::findOrFail($id);
            $oldStatus = $post->status;

            // If transitioning to published without a published_at timestamp, set it now
            if (($data['status'] ?? null) === 'published' && empty($post->published_at) && empty($data['published_at'])) {
                $data['published_at'] = now();
            }

            $post->update($data);

            // If an author updates a draft/scheduled post to published directly, dispatch the job!
            if ($oldStatus !== 'published' && $post->status === 'published') {
                ProcessPostPublishingJob::dispatch($post);
            }

            return new PostResource($post);
        });
    }

    /**
     * Delete a post by ID.
     */
    public function delete(int $id): bool
    {
        $post = Post::findOrFail($id);


        return (bool) $post->delete();
    }
}



?>