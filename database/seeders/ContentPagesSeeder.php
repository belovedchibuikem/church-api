<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use App\Models\ContentPage;
use Illuminate\Database\Seeder;

class ContentPagesSeeder extends Seeder
{
    public function run(): void
    {
        $publishedAt = now()->utc();

        foreach ($this->pages() as $definition) {
            $page = ContentPage::query()->where('slug', $definition['slug'])->first();
            if ($page === null) {
                $page = new ContentPage;
            }

            $page->forceFill([
                'slug' => $definition['slug'],
                'title' => $definition['title'],
                'summary' => $definition['summary'],
                'body' => $definition['body'],
                'locale' => 'en',
                'published_at' => $publishedAt,
            ])->save();

            $page->items()->delete();

            foreach ($definition['items'] as $index => $item) {
                $row = new ContentItem;
                $row->forceFill([
                    'page_id' => $page->getKey(),
                    'kind' => $item['kind'],
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'meta' => $item['meta'] ?? null,
                    'href' => $item['href'] ?? null,
                    'sort_order' => $index,
                    'published_at' => $publishedAt,
                ])->save();
            }
        }
    }

    /**
     * @return list<array{slug: string, title: string, summary: string, body: string, items: list<array<string, mixed>>}>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'Family House Connect',
                'summary' => 'Find community, grow in faith, and serve across the Family House network.',
                'body' => 'Welcome to Family House Connect — churches, missions, Kingdom training, and resources for every believer.',
                'items' => [
                    ['kind' => 'heading', 'title' => 'Our Four Pillars', 'body' => 'Everything you need to belong, grow, and multiply.', 'meta' => ['section' => 'pillars']],
                    ['kind' => 'heading', 'title' => 'Get Started', 'body' => 'Take your next Kingdom step.', 'meta' => ['section' => 'actions']],
                    ['kind' => 'metric', 'title' => 'Lives Impacted', 'body' => '50,000+', 'meta' => ['change' => '+12.4%']],
                    ['kind' => 'metric', 'title' => 'Active Churches', 'body' => '1,842', 'meta' => ['change' => '+8.1%']],
                    ['kind' => 'metric', 'title' => 'Countries', 'body' => '147', 'meta' => ['change' => '+3.2%']],
                    ['kind' => 'metric', 'title' => 'Members', 'body' => '2M+', 'meta' => ['change' => '+15.6%']],
                    ['kind' => 'pillar', 'title' => 'Church', 'body' => 'Find community, worship together, and grow in Jesus Christ through Family House churches worldwide.', 'href' => '/church', 'meta' => ['icon' => '⛪']],
                    ['kind' => 'pillar', 'title' => 'Mission', 'body' => 'Reach the lost and transform lives through crusades, compassion, and church planting.', 'href' => '/mission', 'meta' => ['icon' => '🌍']],
                    ['kind' => 'pillar', 'title' => 'KCA', 'body' => 'Kingdom-minded learning, leadership formation, and practical ministry training.', 'href' => '/kca/gate', 'meta' => ['icon' => '🎓']],
                    ['kind' => 'pillar', 'title' => 'Press', 'body' => 'Books, sermons, devotionals, and digital resources for every season of faith.', 'href' => '/press', 'meta' => ['icon' => '📖']],
                    ['kind' => 'card', 'title' => 'Find a Church', 'body' => 'Locate a conventional, home, or online church near you.', 'href' => '/find-church', 'meta' => ['icon' => '⌖']],
                    ['kind' => 'card', 'title' => 'Grow & Serve', 'body' => 'Join a ministry, start serving, and deepen your walk with Christ.', 'href' => '/account/journey', 'meta' => ['icon' => '♡']],
                    ['kind' => 'card', 'title' => 'Join a Community', 'body' => 'Connect with believers who will walk with you in faith and purpose.', 'href' => '/join-church', 'meta' => ['icon' => '◎']],
                    ['kind' => 'card', 'title' => 'Make an Impact', 'body' => 'Give, pray, and partner with missions transforming nations.', 'href' => '/give', 'meta' => ['icon' => '✦']],
                ],
            ],
            [
                'slug' => 'about',
                'title' => 'About Family House',
                'summary' => 'Who we are and how we serve the Body of Christ worldwide.',
                'body' => 'Family House Connect unites churches, missions, Kingdom training, and resources so every believer can find community, grow, and serve.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Build', 'body' => 'Plant and strengthen churches that love God and people.', 'href' => '/church', 'meta' => ['icon' => '🏛']],
                    ['kind' => 'card', 'title' => 'Equip', 'body' => 'Train believers through KCA and practical ministry.', 'href' => '/kca', 'meta' => ['icon' => '🎓']],
                    ['kind' => 'card', 'title' => 'Send', 'body' => 'Mobilize teams for crusades, outreach, and compassion.', 'href' => '/mission', 'meta' => ['icon' => '🚀']],
                    ['kind' => 'card', 'title' => 'Multiply', 'body' => 'Raise disciples who raise disciples in every place.', 'href' => '/global-journey', 'meta' => ['icon' => '🌱']],
                ],
            ],
            [
                'slug' => 'faq',
                'title' => 'Frequently Asked Questions',
                'summary' => 'Answers to common questions about Family House Connect.',
                'body' => 'Browse answers about churches, giving, KCA, and member tools.',
                'items' => [
                    ['kind' => 'faq', 'title' => 'What is Family House Connect?', 'body' => 'Family House Connect is a global ministry platform that unites churches, missions, Kingdom training, and resources so every believer can find community, grow, and serve.'],
                    ['kind' => 'faq', 'title' => 'How do I find a church near me?', 'body' => 'Use Find a Church to search by city, region, or your current location. You can filter by conventional church, home church, online church, or mission location.'],
                    ['kind' => 'faq', 'title' => 'Can I start a church in my home?', 'body' => 'Yes. Begin at Start a Home Church, confirm eligibility, and complete the guided application.'],
                    ['kind' => 'faq', 'title' => 'How can I give securely?', 'body' => 'Use Give to choose an amount, fund, and payment method. Receipts and recurring giving are available in your member account after sign-in.'],
                    ['kind' => 'faq', 'title' => 'What is Kingdom Citizens Academy?', 'body' => 'KCA is Family House training for discipleship and ministry. Apply from the KCA gate, complete modules with a mentor, and earn a verifiable certificate.'],
                    ['kind' => 'faq', 'title' => 'How do I join Online Church?', 'body' => 'Open Online Church for the Sunday celebration stream, sermon archive, and midweek prayer. Create an account to save your journey and giving.'],
                    ['kind' => 'faq', 'title' => 'Can I verify a KCA certificate?', 'body' => 'Yes. Use Verify a Certificate and enter the public verification code printed on the certificate.'],
                ],
            ],
            [
                'slug' => 'sermons',
                'title' => 'Sermons',
                'summary' => 'Watch and download messages from across the Family House network.',
                'body' => 'Sunday sermon archive and featured messages.',
                'items' => [
                    ['kind' => 'sermon', 'title' => 'Walking in God’s Purpose', 'body' => 'Pastor Daniel David · May 19, 2024 · 48 min', 'href' => '/online-church/sermons/walking-in-gods-purpose', 'meta' => ['label' => 'Latest', 'duration' => '48:12']],
                    ['kind' => 'sermon', 'title' => 'Faith That Moves Mountains', 'body' => 'Pastor Grace Ezekiel · May 12, 2024 · 41 min', 'href' => '/online-church/sermons/faith-that-moves-mountains', 'meta' => ['label' => 'Popular', 'duration' => '41:05']],
                    ['kind' => 'sermon', 'title' => 'The Power of Agreement', 'body' => 'Pastor Samuel Ade · May 5, 2024 · 52 min', 'href' => '/online-church/sermons/the-power-of-agreement', 'meta' => ['label' => 'Series', 'duration' => '52:30']],
                ],
            ],
            [
                'slug' => 'partners',
                'title' => 'Mission Partners',
                'summary' => 'Organizations partnering with Family House missions.',
                'body' => 'Strategic partners advancing evangelism, discipleship, and compassion.',
                'items' => [
                    ['kind' => 'partner', 'title' => 'LoveWorld Outreach', 'body' => 'Evangelism · Discipleship · Media missions across Africa and Europe.', 'href' => '/mission/partners/loveworld-outreach', 'meta' => ['status' => 'Active']],
                    ['kind' => 'partner', 'title' => 'Kingdom Builders Network', 'body' => 'Church planting support and missionary care in 32 nations.', 'href' => '/mission/partners/kingdom-builders-network', 'meta' => ['status' => 'Active']],
                    ['kind' => 'partner', 'title' => 'Compassion Fields', 'body' => 'Humanitarian relief, education, and community health programmes.', 'href' => '/mission/partners/compassion-fields', 'meta' => ['status' => 'Active']],
                ],
            ],
            [
                'slug' => 'projects',
                'title' => 'Mission Projects',
                'summary' => 'Active mission funding projects.',
                'body' => 'Support church construction, clean water, and missionary care.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Building Hope Church', 'body' => 'Raised ₦12.4M of ₦18M · Church construction in Enugu.', 'href' => '/mission/projects/building-hope-church', 'meta' => ['status' => 'In Progress']],
                    ['kind' => 'card', 'title' => 'Clean Water for Villages', 'body' => 'Raised ₦4.8M of ₦6M · Boreholes for mission communities.', 'href' => '/mission/projects/clean-water-for-villages', 'meta' => ['status' => 'In Progress']],
                    ['kind' => 'card', 'title' => 'Missionary Care Fund', 'body' => 'Raised ₦9.1M of ₦10M · Monthly support for field workers.', 'href' => '/mission/projects/missionary-care-fund', 'meta' => ['status' => 'Almost There']],
                ],
            ],
            [
                'slug' => 'stories',
                'title' => 'Mission Stories',
                'summary' => 'Testimonies and impact stories from the field.',
                'body' => 'Real stories of healing, growth, and Kingdom impact.',
                'items' => [
                    ['kind' => 'card', 'title' => 'A Miracle of Healing', 'body' => 'After months of prayer at the Lagos crusade, Ada walked again — and now leads a home church.', 'href' => '/mission/stories/a-miracle-of-healing', 'meta' => ['label' => 'Testimony']],
                    ['kind' => 'card', 'title' => 'From One Living Room to a Nation', 'body' => 'What began with eight people in Ikeja is now a multiplying family across continents.', 'href' => '/global-journey', 'meta' => ['label' => 'Journey']],
                    ['kind' => 'card', 'title' => 'Youth Who Found Purpose', 'body' => 'KCA graduates planted three new fellowships in one academic year.', 'href' => '/mission/stories/youth-who-found-purpose', 'meta' => ['label' => 'KCA Impact']],
                ],
            ],
            [
                'slug' => 'online-church',
                'title' => 'Online Church',
                'summary' => 'Live worship, sermons, and midweek gatherings online.',
                'body' => 'Join Family House Online Church for celebration services, prayer, and teaching.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Sunday Celebration', 'body' => 'Sun · 10:00 AM WAT with Pastor Daniel David', 'href' => '/online-church/live'],
                    ['kind' => 'card', 'title' => 'Sermons', 'body' => 'Catch up on messages anytime.', 'href' => '/online-church/sermons'],
                    ['kind' => 'card', 'title' => 'Power in Prayer', 'body' => 'Wed · 7:00 PM WAT with the Prayer Team', 'href' => '/online-church/prayer'],
                    ['kind' => 'sermon', 'title' => 'Walking in God’s Purpose', 'body' => 'Pastor Daniel David · May 19, 2024 · 48 min', 'href' => '/online-church/sermons/walking-in-gods-purpose'],
                ],
            ],
            [
                'slug' => 'church',
                'title' => 'Church',
                'summary' => 'Find community, worship together, and grow in Jesus Christ.',
                'body' => 'Family House churches gather in city campuses, homes, and online so every believer can belong.',
                'items' => [
                    ['kind' => 'pillar', 'title' => 'Find a Church', 'body' => 'Search conventional, home, and online churches across the network.', 'href' => '/find-church', 'meta' => ['icon' => '⌖']],
                    ['kind' => 'pillar', 'title' => 'Start a Home Church', 'body' => 'Host a gathering in your living room with pastoral covering.', 'href' => '/start-home-church', 'meta' => ['icon' => '⌂']],
                    ['kind' => 'pillar', 'title' => 'Join Online', 'body' => 'Celebrate live every Sunday with the global Family House family.', 'href' => '/online-church', 'meta' => ['icon' => '▶']],
                ],
            ],
            [
                'slug' => 'mission',
                'title' => 'Mission',
                'summary' => 'Crusades, compassion, and church planting across nations.',
                'body' => 'Family House missions reach cities through crusades, partners, and funded field projects.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Crusades', 'body' => 'Upcoming harvest gatherings in Lagos, Accra, and Nairobi.', 'href' => '/mission/crusades', 'meta' => ['icon' => '🌍']],
                    ['kind' => 'card', 'title' => 'Partners', 'body' => 'Organizations advancing evangelism and discipleship with us.', 'href' => '/mission/partners', 'meta' => ['icon' => '🤝']],
                    ['kind' => 'card', 'title' => 'Projects', 'body' => 'Fund church buildings, clean water, and missionary care.', 'href' => '/mission/projects', 'meta' => ['icon' => '🏗']],
                ],
            ],
            [
                'slug' => 'kca',
                'title' => 'Kingdom Citizens Academy',
                'summary' => 'Kingdom-minded learning and practical ministry training.',
                'body' => 'KCA forms leaders through modules, mentoring, evidence, and certification.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Why Join KCA?', 'body' => 'A formation path from foundations of faith to mission and discipleship.', 'href' => '/kca/why'],
                    ['kind' => 'card', 'title' => 'Apply', 'body' => 'Complete the guided application and join the next cohort.', 'href' => '/kca/apply'],
                    ['kind' => 'card', 'title' => 'Verify a Certificate', 'body' => 'Confirm a KCA certificate with its public verification code.', 'href' => '/kca/certificates/verify'],
                ],
            ],
            [
                'slug' => 'giving',
                'title' => 'Give',
                'summary' => 'Sow into church, missions, and Kingdom training.',
                'body' => 'Tithes, offerings, missions, and project gifts all flow through Family House Connect.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Tithe & Offering', 'body' => 'Honour God with the first fruits of your increase.', 'href' => '/give'],
                    ['kind' => 'card', 'title' => 'Missions', 'body' => 'Send crusade teams and care for field workers.', 'href' => '/give?fund=missions'],
                    ['kind' => 'card', 'title' => 'Projects', 'body' => 'Help finish church buildings and community wells.', 'href' => '/mission/projects'],
                ],
            ],
            [
                'slug' => 'press',
                'title' => 'Kingdom Press',
                'summary' => 'Books, devotionals, and teaching resources for every season of faith.',
                'body' => 'Kingdom Press publishes leadership, prayer, worship, and discipleship titles used across the Family House network.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Browse titles', 'body' => 'Leadership, prayer, worship, and Christian living from Kingdom Press.', 'href' => '/press', 'meta' => ['icon' => '📘']],
                    ['kind' => 'card', 'title' => 'Sermons', 'body' => 'Watch and download messages from Family House pulpits.', 'href' => '/online-church/sermons', 'meta' => ['icon' => '▶']],
                    ['kind' => 'card', 'title' => 'Devotionals', 'body' => 'Daily readings that keep households in the Word.', 'href' => '/press', 'meta' => ['icon' => '📖']],
                ],
            ],
            [
                'slug' => 'vision',
                'title' => 'Our Vision',
                'summary' => 'One family. One mission. Every nation.',
                'body' => 'We exist so every believer can find community, grow in Jesus Christ, and serve the nations.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Belong', 'body' => 'Churches and home churches that make family real.'],
                    ['kind' => 'card', 'title' => 'Grow', 'body' => 'KCA, sermons, and pastoral care that form disciples.'],
                    ['kind' => 'card', 'title' => 'Send', 'body' => 'Missions and compassion that reach the lost.'],
                ],
            ],
            [
                'slug' => 'events',
                'title' => 'Events',
                'summary' => 'Gather, worship, learn, and serve together.',
                'body' => 'Family House events bring the family together for training, worship, and Kingdom advancement.',
                'items' => [
                    ['kind' => 'card', 'title' => 'View Calendar', 'body' => 'See upcoming gatherings across the network.', 'href' => '/account/calendar'],
                    ['kind' => 'card', 'title' => 'Register', 'body' => 'Save your seat at conferences, retreats, and summits.', 'href' => '/events'],
                ],
            ],
        ];
    }
}
