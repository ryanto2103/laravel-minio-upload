<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function index()
    {
        $images = Image::latest()->get();
        return view('images.index', compact('images'));
    }

    public function create()
    {
        return view('images.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();

            // Upload ke MinIO (PRIVATE)
            $path = Storage::disk('s3')->putFileAs('images', $file, $filename);

            // Simpan ke database
            Image::create([
                'filename' => $filename,
                'original_name' => $originalName,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize()
            ]);

            return redirect()->route('images.index')
                ->with('success', 'Image uploaded successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $image = Image::findOrFail($id);

        // URL via Laravel proxy (bukan MinIO langsung)
        $imageUrl = url('/image/' . $image->path);

        return view('images.show', compact('image', 'imageUrl'));
    }

    public function destroy($id)
    {
        try {
            $image = Image::findOrFail($id);

            // Hapus dari MinIO
            Storage::disk('s3')->delete($image->path);

            // Hapus dari DB
            $image->delete();

            return redirect()->route('images.index')
                ->with('success', 'Image deleted successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}