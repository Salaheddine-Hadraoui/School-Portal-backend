<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseStorage
{
    protected $base;
    protected $serviceKey;
    protected $bucket;
    protected $endpoint_url_storage;

    public function __construct($bucketEnv)
    {
        $this->base = rtrim(env('SUPABASE_URL'), '/');
        $this->serviceKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $this->bucket = $bucketEnv;
        $this->endpoint_url_storage = '/storage/v1/object/';
    }

    /**
     * Upload an uploaded file to Supabase Storage.
     * Returns the path inside bucket (e.g. courses/filename.pdf).
     */
    public function upload(UploadedFile $file, string $pathPrefix = ''): string
    {
        $filename = Str::random(8) . '-' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
        $remotePath = trim($pathPrefix ? ($pathPrefix . '/' . $filename) : $filename, '/');

        $url = $this->base . $this->endpoint_url_storage . $this->bucket . '/' . $remotePath;


        // PUT raw body with proper content type
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
            'Content-Type' => $file->getClientMimeType(),
        ])->withBody(file_get_contents($file->getRealPath()), $file->getClientMimeType())
            ->put($url);

        if (! $response->successful()) {
            // throw or return false — you can adjust error handling
            Log::error(
                'Supabase upload failed: ',
                [
                    'path' => $url,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]
            );
        }

        return $remotePath; // e.g. abc-file.pdf
    }
    public function delete(string $remotePath)
    {
        $url = $this->base . rtrim($this->endpoint_url_storage, '/') . '/' . $this->bucket . '/' . ltrim($remotePath, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey
        ])->delete($url);

        if (! $response->successful()) {
            Log::error(
                'Supabase delete request failed',
                [
                    'path' => $remotePath,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]
            );
        }
        return;
    }

    /**
     * Generate a signed URL for a private object (expiresIn seconds)
     * Returns the signed URL string.
     */
    public function signedUrl(string $objectPath, int $expiresIn = 60): string
    {
        $url = $this->base . '/storage/v1/object/sign/' . $this->bucket . '/' . $objectPath;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
            'Content-Type' => 'application/json',
        ])->post($url, ['expiresIn' => $expiresIn]);

        if (! $response->successful()) {
            throw new \RuntimeException('Supabase sign URL failed: ' . $response->body());
        }

        $json = $response->json();
        // Supabase returns { signedURL: "..." } or { signedURL } depending on version
        return $json['signedURL'] ?? $json['signedUrl'] ?? ($json['signed_url'] ?? null)
            ?? ($response->body()); // fallback: return raw body for debugging
    }

    /**
     * Check if object exists
     */
    public function exists(string $objectPath): bool
    {
        $url = $this->base . $this->endpoint_url_storage . $this->bucket . '/' . $objectPath;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
        ])->head($url);

        return $response->status() === 200;
    }
}
