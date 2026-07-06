<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicToolsTest extends TestCase
{
    public function test_public_tools_are_visible_to_guests(): void
    {
        foreach ([
            '/calculator' => 'Calculator',
            '/chart' => 'Chart',
            '/speed-test' => 'SpeedTest',
        ] as $path => $component) {
            $this->get($path)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }
}
