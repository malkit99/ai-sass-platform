<?php

namespace App\Http\Controllers\Api\Media;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\Media\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaFileController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', MediaFile::class);

        $data = $request->validate([
            'type' => ['nullable', 'in:image,video,audio,document'],
            'purpose' => ['nullable', Rule::in(MediaStorage::PURPOSES)],
        ]);

        return MediaFile::query()
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($data['purpose'] ?? null, fn ($q, $purpose) => $q->where('purpose', $purpose))
            ->latest()
            ->get();
    }

    /**
     * Current storage usage vs. the account's plan limit — drives the usage
     * bar on the File Manager panel and the upload-time enforcement below.
     */
    public function usage()
    {
        $account = Auth::user()->account;
        $usedBytes = MediaFile::storageUsedBytes($account->id);
        $limitMb = $account->plan?->limits['storage_limit_mb'] ?? 100;

        return response()->json([
            'used_bytes' => $usedBytes,
            'limit_bytes' => $limitMb * 1024 * 1024,
            'limit_mb' => $limitMb,
            'used_mb' => round($usedBytes / 1024 / 1024, 2),
            'percent' => min(100, round($usedBytes / ($limitMb * 1024 * 1024) * 100)),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', MediaFile::class);

        $data = $request->validate([
            // 50MB ceiling — generous enough for WhatsApp-sized media (WhatsApp
            // itself caps video/documents around 16-100MB depending on type).
            'file' => ['required', 'file', 'max:51200'],
            'purpose' => ['nullable', Rule::in(MediaStorage::PURPOSES)],
        ]);

        $account = Auth::user()->account;
        $purpose = $data['purpose'] ?? 'whatsapp_media';
        $file = $data['file'];

        $limitBytes = ($account->plan?->limits['storage_limit_mb'] ?? 100) * 1024 * 1024;
        $usedBytes = MediaFile::storageUsedBytes($account->id);

        if ($usedBytes + $file->getSize() > $limitBytes) {
            $limitMb = (int) round($limitBytes / 1024 / 1024);

            throw ValidationException::withMessages([
                'file' => ["This upload would exceed your plan's storage limit ({$limitMb} MB). Delete some files or upgrade your plan."],
            ]);
        }

        $disk = MediaStorage::disk($purpose);
        $path = MediaStorage::pathPrefix($purpose).'/'.date('Y/m').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $disk->put($path, file_get_contents($file->getRealPath()));

        $media = MediaFile::create([
            'purpose' => $purpose,
            'disk' => MediaStorage::diskName(),
            'path' => $path,
            'url' => $disk->url($path),
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => MediaFile::typeFromMime($file->getMimeType()),
        ]);

        return response()->json($media, 201);
    }

    public function destroy(MediaFile $file)
    {
        $this->authorize('delete', $file);

        $file->delete();

        return response()->noContent();
    }
}
