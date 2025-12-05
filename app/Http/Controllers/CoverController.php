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
        $validTypes = ['anime', 'audio', 'audiosample', 'book', 'console', 'games', 'movies', 'music', 'preview', 'sample', 'tvrage', 'video', 'xxx', 'tvshows'];

        if (! in_array($type, $validTypes)) {
            abort(404);
        }

        // Build the file path
        // For preview and sample images, try with _thumb suffix first
        if (in_array($type, ['preview', 'sample'])) {
            $pathInfo = pathinfo($filename);
            $thumbFilename = $pathInfo['filename'].'_thumb.'.($pathInfo['extension'] ?? 'jpg');
            $thumbPath = storage_path("covers/{$type}/{$thumbFilename}");

            // Use thumb path if it exists, otherwise fall back to original filename
            if (file_exists($thumbPath)) {
                $filePath = $thumbPath;
            } else {
                $filePath = storage_path("covers/{$type}/{$filename}");
            }
        } elseif ($type === 'anime') {
            // For anime, try the requested filename first, then fall back to old format (without -cover)
            $filePath = storage_path("covers/{$type}/{$filename}");
            
            // If file doesn't exist and filename ends with -cover.jpg, try old format
            if (!file_exists($filePath) && preg_match('/^(\d+)-cover\.jpg$/', $filename, $matches)) {
                $oldFormatPath = storage_path("covers/{$type}/{$matches[1]}.jpg");
                if (file_exists($oldFormatPath)) {
                    $filePath = $oldFormatPath;
                }
            }
        } else {
            $filePath = storage_path("covers/{$type}/{$filename}");
        }

        // Check if file exists
        if (! file_exists($filePath)) {
            // Return placeholder image
            $placeholderPath = public_path('assets/images/no-cover.png');
            if (file_exists($placeholderPath)) {
                return response()->file($placeholderPath);
            }
            abort(404);
        }

        // Determine content type
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        // Serve the file with proper headers
        return response()->file($filePath, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000', // Cache for 1 year
        ]);
    }
}
