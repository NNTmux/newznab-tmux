<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoverController extends Controller
{
    /**
     * Serve cover images from storage
     *
     * @param  string  $type  The type of cover (movies, console, music, etc.)
     * @param  string  $filename  The filename of the cover image
     * @return BinaryFileResponse|Response
     */
    public function show(string $type, string $filename)
    {
        // Validate cover type
        $validTypes = ['anime', 'audio', 'audiosample', 'book', 'console', 'games', 'movies', 'music', 'preview', 'sample', 'tvrage', 'video', 'xxx'];

        if (! in_array($type, $validTypes)) {
            abort(404);
        }

        // Build the file path
        $filePath = storage_path("covers/{$type}/{$filename}");

        // Check if file exists
        if (! file_exists($filePath)) {
            // Return placeholder image
            $placeholderPath = public_path('assets/images/no-cover.png');
            if (file_exists($placeholderPath)) {
                return response()->file($placeholderPath);
            }
            abort(404);
        }

        // Serve the file with proper headers
        return response()->file($filePath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000', // Cache for 1 year
        ]);
    }
}
