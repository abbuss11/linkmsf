<?php

namespace App\Jobs;

use App\Models\JobPosting;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

/**
 * Share image of an offer for WhatsApp, Facebook and LinkedIn (§4.1):
 * 1200×630 PNG in the organisation's colour, generated with GD. Idempotent:
 * regenerating overwrites the same file.
 */
class GenerateShareImage extends OrganizationJob
{
    public int $tries = 3;

    public function __construct(public JobPosting $posting)
    {
        $this->forOrganizationOf($posting);
    }

    public function handle(): void
    {
        $posting = JobPosting::query()->find($this->posting->getKey());

        if ($posting === null) {
            return;
        }

        $organization = Organization::query()->findOrFail($posting->organization_id);
        $png = self::render($posting, $organization);
        $path = 'share/'.$posting->ulid.'.png';

        Storage::disk('public')->put($path, $png);
        $posting->forceFill(['share_image_path' => $path])->saveQuietly();
    }

    public static function render(JobPosting $posting, Organization $organization): string
    {
        $width = 1200;
        $height = 630;
        $image = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = sscanf($organization->primary_color ?: '#0f766e', '#%02x%02x%02x') ?: [15, 118, 110];
        $background = imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
        $white = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocatealpha($image, 255, 255, 255, 40);
        imagefill($image, 0, 0, $background);

        $regular = resource_path('fonts/DejaVuSans.ttf');
        $bold = resource_path('fonts/DejaVuSans-Bold.ttf');

        imagettftext($image, 30, 0, 70, 110, $soft, $bold, mb_strtoupper($organization->name));

        $y = 220;
        foreach (array_slice(self::wrap($posting->title, $bold, 58, $width - 140), 0, 3) as $line) {
            imagettftext($image, 58, 0, 70, $y, $white, $bold, $line);
            $y += 82;
        }

        $footer = array_filter([
            $posting->type->getLabel(),
            $posting->location,
            $posting->closes_at ? __('showcase.share.deadline', ['date' => $posting->closes_at->timezone($organization->timezone)->translatedFormat('j F Y')]) : null,
        ]);
        imagettftext($image, 28, 0, 70, $height - 70, $white, $regular, implode('  ·  ', $footer));
        imagettftext($image, 28, 0, $width - 170, $height - 70, $soft, $bold, 'LINK');

        ob_start();
        imagepng($image, null, 6);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate) ?: [0, 0, 0, 0, 0, 0, 0, 0];

            if ($maxWidth < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }
}
