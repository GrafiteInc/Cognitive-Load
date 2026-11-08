<?php

namespace Tests\SloppyFixtures;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class EloquentMisuse
{
    public function notify(array $ids): void
    {
        foreach ($ids as $id) {
            $user = User::find($id);
            $user->notify();
        }
    }

    public function activeCount(): int
    {
        return User::all()->where('active', true)->count();
    }

    public function authors(): array
    {
        $names = [];

        foreach (Post::query()->latest()->get() as $post) {
            $names[] = $post->author->name;
        }

        return $names;
    }

    public function store(Request $request): Post
    {
        return Post::create($request->all());
    }

    public function timeout(): int
    {
        return (int) env('API_TIMEOUT', 30);
    }
}
