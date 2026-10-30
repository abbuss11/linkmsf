<?php

namespace App\Support\Applications;

use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\RulesBuilder;
use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Storage rules for application files (§10.1): private disk, UUID names,
 * real MIME type detected from the content, extension derived from it.
 */
class DocumentFiles
{
    public const EXTENSION_FOR_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public static function disk(): string
    {
        return (string) config('link.documents_disk');
    }

    public static function normalizedExtension(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    public static function cleanName(string $fileName): string
    {
        $name = Str::limit(basename(str_replace('\\', '/', $fileName)), 180, '');

        return preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'document';
    }

    public static function detectMime(string $absolutePath): ?string
    {
        return MimeTypes::getDefault()->guessMimeType($absolutePath);
    }

    public static function acceptsMime(FieldDefinition $field, ?string $mime): bool
    {
        return $mime !== null && in_array($mime, app(RulesBuilder::class)->mimeTypes($field->file['extensions']), true);
    }

    /**
     * Moves a verified local file to the documents disk under a UUID name.
     */
    public static function store(Application $application, string $absolutePath, string $mime): string
    {
        $path = sprintf('applications/%d/%s/%s.%s', $application->organization_id, $application->ulid, Str::uuid(), self::EXTENSION_FOR_MIME[$mime] ?? 'bin');
        $stream = fopen($absolutePath, 'rb');
        Storage::disk(self::disk())->put($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $path;
    }

    public static function chunkPath(string $documentUlid): string
    {
        return Storage::disk('local')->path('chunks/'.$documentUlid.'.part');
    }
}
