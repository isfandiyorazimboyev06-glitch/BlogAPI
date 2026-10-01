<?php

namespace App\Exports;

use App\Models\Post;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PostsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        protected array $postIds = []
    ) {}

    public function collection(): Collection
    {
        if (!empty($this->postIds)) {
            return Post::whereIn('id', $this->postIds)->orderBy('id', 'asc')->get();
        }

        // Export ALL published posts in the system into the single master spreadsheet
        return Post::where('status', 'published')->orderBy('id', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'Content',
            'Status',
            'Published At',
            'Created At',
        ];
    }

    public function map($post): array
    {
        return [
            $post->id,
            $post->title,
            $post->content,
            $post->status,
            $post->published_at?->format('Y-m-d H:i:s') ?? 'N/A',
            $post->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
