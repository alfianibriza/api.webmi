<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class MediaController extends Controller
{
    public function index()
    {
        $directories = ['teachers', 'students', 'posts', 'school', 'schedules', 'sliders', 'images', 'uploads'];
        $files = [];

        foreach ($directories as $dir) {
            if (Storage::disk('public')->exists($dir)) {
                $filePaths = Storage::disk('public')->files($dir);
                foreach ($filePaths as $path) {
                    $mime = Storage::disk('public')->mimeType($path);
                    // Include all files, not just images.
                    $files[] = [
                        'name' => basename($path),
                        'path' => $path,
                        'url' => Storage::url($path),
                        'size' => Storage::disk('public')->size($path),
                        'last_modified' => Storage::disk('public')->lastModified($path),
                        'mime' => $mime, // Return mime type for frontend handling
                    ];
                }
            }
        }

        // Sort by newest first
        usort($files, function ($a, $b) {
            return $b['last_modified'] <=> $a['last_modified'];
        });

        return response()->json($files);
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Media Upload Request:', [
            'all' => $request->all(),
            'files' => $request->allFiles(),
            'headers' => $request->headers->all()
        ]);

        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar|max:10240', // 10MB
            'folder' => 'nullable|string'
        ]);

        $file = $request->file('image');
        $folder = $request->input('folder', 'uploads');

        // Ensure we are saving to a public accessible directory
        // Using storage_path('app/public') which is linked to public/storage
        $destinationPath = storage_path('app/public/' . $folder);

        // Create directory if not exists
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        // Generate distinctive filename
        $filename = time() . '_' . $file->getClientOriginalName();

        // Move file
        $file->move($destinationPath, $filename);

        // Construct path relative to public disk root for Storage::url to work, 
        // or just construct the URL manually if we are bypassing Storage logic completely.
        // However, standard Laravel apps symlink public/storage -> storage/app/public.
        // So the relative path for database/response should be "$folder/$filename"
        $relativePath = $folder . '/' . $filename;

        return response()->json([
            'message' => 'File uploaded successfully',
            'file' => [
                'name' => $filename,
                'path' => $relativePath,
                'url' => asset('storage/' . $relativePath), // Use asset helper for direct URL
            ]
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = $request->input('path');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['message' => 'File deleted successfully']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }
}
