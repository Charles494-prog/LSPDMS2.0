<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_redirects_to_the_intro_page(): void
    {
        $this->get('/')->assertRedirect('/intro');

        $this->get('/intro')->assertOk();
    }
}
