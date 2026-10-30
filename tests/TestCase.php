<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests do not need compiled front-end assets.
        $this->withoutVite();

        // Files written by tests (share images, documents) never reach the
        // real storage folders.
        Storage::fake('public');
        Storage::fake('local');
    }
}
