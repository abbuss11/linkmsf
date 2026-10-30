<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Slugs appear in /app/{slug} and /o/{slug}: they must not collide with
 * panel routes and are unique even among soft-deleted organisations.
 */
class OrganizationSlug
{
    public const RESERVED = [
        'admin', 'app', 'login', 'logout', 'register', 'password-reset', 'email-verification',
        'multi-factor-authentication', 'profile', 'new', 'offres', 'o', 'api', 'horizon',
    ];

    /**
     * @return list<mixed>
     */
    public static function rules(?Organization $ignore = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:60',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(self::RESERVED),
            Rule::unique('organizations', 'slug')->ignore($ignore?->getKey()),
        ];
    }

    public static function suggest(string $name): string
    {
        $base = Str::limit(Str::slug($name), 50, '') ?: 'organisation';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED, true) || Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
