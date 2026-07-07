<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_speed_test_page_does_not_require_login(): void
    {
        $this->get('/speed-test')->assertOk();
    }

    public function test_about_page_is_public(): void
    {
        $this->get('/about')->assertOk();
    }

    public function test_article_alias_redirects_to_articles_index(): void
    {
        $this->get('/article')->assertRedirect(route('articles.index'));
    }
}
