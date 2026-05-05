<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AdminPostController extends Controller
{
    public function index()
    {
        $posts = Post::latest()->get();
        return \Inertia\Inertia::render('Dashboard/Post/Index', [
            'posts' => $posts
        ]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('Dashboard/Post/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
            'image' => 'nullable', // Allow string or file
            'created_at' => 'nullable|date',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            try {
                $imagePath = \App\Services\ImageService::upload($request->file('image'), 'posts');
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar. Format tidak didukung.']);
            }
        } elseif ($request->filled('image')) {
            $imagePath = $request->input('image');
        }

        Post::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'content' => $request->content,
            'image' => $imagePath,
            'user_id' => auth()->id(),
            'status' => 'published',
            'created_at' => $request->created_at ? date('Y-m-d H:i:s', strtotime($request->created_at)) : now(),
        ]);

        return redirect()->route('admin.post.index')->with('success', 'Berita berhasil dipublish!');
    }
    public function edit(Post $post)
    {
        return \Inertia\Inertia::render('Dashboard/Post/Edit', [
            'post' => $post
        ]);
    }

    public function update(Request $request, Post $post)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
            'image' => 'nullable', // Allow string or file
            'status' => 'required|in:draft,published',
            'created_at' => 'nullable|date',
        ]);

        $data = [
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'content' => $request->content,
            'status' => $request->status,
        ];

        if ($request->created_at) {
            $data['created_at'] = date('Y-m-d H:i:s', strtotime($request->created_at));
        }

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048']);
            // Delete old image
            \App\Services\ImageService::delete($post->image);

            try {
                $data['image'] = \App\Services\ImageService::upload($request->file('image'), 'posts');
            } catch (\Exception $e) {
                return back()->withErrors(['image' => 'Gagal mengupload gambar.']);
            }
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        } elseif ($request->has('image') && $request->input('image') === null) {
            // Explicitly set to null if cleared
            \App\Services\ImageService::delete($post->image);
            $data['image'] = null;
        }

        $post->update($data);

        return redirect()->route('admin.post.index')->with('success', 'Berita berhasil diperbarui!');
    }

    public function destroy(Post $post)
    {
        \App\Services\ImageService::delete($post->image);
        $post->delete();

        return redirect()->route('admin.post.index')->with('success', 'Berita berhasil dihapus!');
    }

    public function deleteImage(Post $post)
    {
        if ($post->image) {
            \App\Services\ImageService::delete($post->image);
            $post->update(['image' => null]);
            return back()->with('success', 'Foto berita berhasil dihapus!');
        }
        return back()->with('error', 'Berita tidak memiliki foto.');
    }
}
