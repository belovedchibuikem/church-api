<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicContentApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gets_published_content_page_with_items(): void
    {
        $page = new ContentPage;
        $page->forceFill([
            'slug' => 'faq',
            'title' => 'Frequently Asked Questions',
            'summary' => 'Common questions',
            'body' => 'FAQ body',
            'locale' => 'en',
            'published_at' => now()->utc()->subMinute(),
        ])->save();

        $item = new ContentItem;
        $item->forceFill([
            'page_id' => $page->getKey(),
            'kind' => 'faq',
            'title' => 'What is Family House Connect?',
            'body' => 'A global ministry platform.',
            'meta' => null,
            'href' => null,
            'sort_order' => 0,
            'published_at' => now()->utc()->subMinute(),
        ])->save();

        $this->getJson('/api/v1/content/pages/faq')
            ->assertOk()
            ->assertJsonPath('data.slug', 'faq')
            ->assertJsonPath('data.title', 'Frequently Asked Questions')
            ->assertJsonPath('data.items.0.kind', 'faq')
            ->assertJsonPath('data.items.0.title', 'What is Family House Connect?');

        $this->getJson('/api/v1/content/pages')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'faq');
    }

    public function test_unpublished_content_page_is_not_found(): void
    {
        $page = new ContentPage;
        $page->forceFill([
            'slug' => 'draft-only',
            'title' => 'Draft',
            'summary' => null,
            'body' => 'Hidden',
            'locale' => 'en',
            'published_at' => null,
        ])->save();

        $this->getJson('/api/v1/content/pages/draft-only')->assertNotFound();
    }
}
