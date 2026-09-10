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
                    ['kind' => 'faq', 'title' => 'What is Kingdom Change Agents?', 'body' => 'KCA is Family House training for discipleship and ministry. Apply from the KCA gate, complete modules with a mentor, and earn a verifiable certificate.'],
                    ['kind' => 'faq', 'title' => 'How do I join Online Church?', 'body' => 'Open Online Church for the Sunday celebration stream, sermon archive, and midweek prayer. Create an account to save your journey and giving.'],
                    ['kind' => 'faq', 'title' => 'Can I verify a KCA certificate?', 'body' => 'Yes. Use Verify a Certificate and enter the public verification code printed on the certificate.'],
                    ['kind' => 'faq', 'title' => 'Where can I read the privacy policy?', 'body' => 'Open Privacy Policy for how we handle personal data, or Privacy Controls in your account to request an export or deletion.'],
                    ['kind' => 'faq', 'title' => 'How are gifts and refunds handled?', 'body' => 'Tithes, offerings, and mission gifts are processed through Give. See Giving Policy for receipts, designated funds, and how to report a duplicate or mistaken payment.'],
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
                    ['kind' => 'card', 'title' => 'Apply', 'body' => 'Complete the guided application and join the next cohort.', 'href' => '/kca/enrol'],
                    ['kind' => 'card', 'title' => 'Verify a Certificate', 'body' => 'Confirm a KCA certificate with its public verification code.', 'href' => '/kca/certificates/verify'],
                ],
            ],
            [
                'slug' => 'giving',
                'title' => 'Give',
                'summary' => 'Sow into church, missions, and Kingdom training.',
                'body' => 'Tithes, offerings, missions, and project gifts all flow through Family House Connect.',
                'items' => [
                    ['kind' => 'card', 'title' => 'Tithe', 'body' => 'Honour God with the first fruits of your increase.', 'href' => '/give'],
                    ['kind' => 'card', 'title' => 'Offering', 'body' => 'Sow into local church work as its own gift.', 'href' => '/give'],
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
                    ['kind' => 'card', 'title' => 'Publications', 'body' => 'Leadership, prayer, worship, and Christian living from Kingdom Press.', 'href' => '/press/publications', 'meta' => ['icon' => '📘']],
                    ['kind' => 'card', 'title' => 'Sermons', 'body' => 'Watch and download messages from Family House pulpits.', 'href' => '/online-church/sermons', 'meta' => ['icon' => '▶']],
                    ['kind' => 'card', 'title' => 'Devotionals', 'body' => 'Daily readings and Study Manuals. Each title shows its type.', 'href' => '/press/devotionals', 'meta' => ['icon' => '📖']],
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
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'summary' => 'How Family House Connect collects, uses, and protects personal information.',
                'body' => 'Family House Connect (“we”, “us”) operates a ministry platform for local churches, home fellowships, online gatherings, missions, giving, and Kingdom Citizens Academy (KCA). This Privacy Policy describes the personal information we process when you use the public website, member account, admin tools, or related mobile apps. Last updated: 8 September 2026.',
                'items' => $this->blocks([
                    ['Who we are', 'Family House Connect is a global ministry network. For privacy questions, contact hello@familyhouseconnect.org. Operational headquarters are listed on the Contact page.'],
                    ['Information we collect', 'Depending on how you use the platform we may collect: identity and contact details; location used to find a church; membership, groups, and ministry involvement; KCA applications, enrolment, attendance, and certificates; giving amounts, funds, and receipts; prayer requests, need requests, and testimonies; messages and notifications preferences; guardian or child-account links where safeguarding requires them; and technical data such as device, session, and security logs.'],
                    ['How we use information', 'We use personal information to create and secure accounts, connect you with churches and online gatherings, process giving, administer KCA and events, provide pastoral and operational tools to authorised leaders, send the communications you consent to, improve the service, and meet legal, financial, and safeguarding obligations.'],
                    ['Legal bases', 'Where data-protection law applies, we process information because you asked for a service (contract), because we have a legitimate ministry interest (for example securing accounts or finding a nearby church), because you consented (marketing or optional analytics), or because we must comply with law or protect vital interests in a safeguarding emergency.'],
                    ['Churches, giving, and KCA', 'Leaders of a church or home church you join may see membership and ministry records needed to care for that community. Finance teams see giving needed to issue receipts. KCA administrators and assigned lecturers see study records required to teach, assess, and certify. We do not sell personal information.'],
                    ['Children and safeguarding', 'Family House Connect is not directed at children acting without a parent or guardian. Child profiles, guardian links, and communication restrictions exist to protect minors. Safeguarding reports may be retained and shared with authorised staff or authorities as required by law. See our Safeguarding page.'],
                    ['Sharing', 'We share information with churches and ministries in the Family House network that you join or apply to; payment processors for gifts; hosting, email, and security providers who process data on our instructions; and authorities when required by law or to prevent serious harm.'],
                    ['Retention and security', 'We keep information for as long as your account is active and as needed for pastoral, financial, certification, and legal records, then delete or anonymise it according to our retention rules. We use access controls, encryption in transit, session protection, and audit logging. No method of transmission is perfectly secure.'],
                    ['Your rights', 'Subject to applicable law you may access, correct, export, or request deletion of personal data, withdraw consents, and object to certain processing. Signed-in members can start a request from Privacy Controls (multi-factor authentication may be required). Some records — for example completed gifts, issued certificates, or safeguarding files — may be retained where law or ministry duty requires it.'],
                    ['Cookies and international use', 'See the Cookie Policy for cookies used on the website. Family House Connect serves members in many countries; information may be stored or accessed in locations other than where you live, with safeguards appropriate to the transfer.'],
                    ['Changes', 'We may update this policy as the platform grows. The “last updated” date will change, and material updates may be announced in the product or by email when required.'],
                ]),
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Use',
                'summary' => 'The agreement that governs Family House Connect websites, apps, and ministry tools.',
                'body' => 'These Terms of Use govern access to Family House Connect. By creating an account, joining a church, applying to KCA, giving, or otherwise using the platform you agree to these terms and to the Privacy Policy. Last updated: 8 September 2026.',
                'items' => $this->blocks([
                    ['The service', 'Family House Connect provides tools for worship, community, discipleship, missions, giving, events, and Kingdom training. We may change features as ministry needs evolve. Some areas require a signed-in account, church membership, or staff permissions.'],
                    ['Your account', 'You must provide accurate information, keep credentials confidential, and enable extra security (such as multi-factor authentication) when offered for sensitive actions. You are responsible for activity under your account unless you promptly report misuse.'],
                    ['Acceptable use', 'Use the platform in a way that honours Christ and the law. You must not harass or defame others, upload unlawful or sexually exploitative content, impersonate a person or church, interfere with security, scrape private data, or misuse giving, certificates, or pastoral records.'],
                    ['Content you submit', 'Prayer requests, testimonies, messages, and similar content remain yours, but you grant Family House Connect a licence to host and display them for ministry purposes. Do not submit information about others without a proper basis. We may remove content that breaks these terms or our Community Guidelines.'],
                    ['Giving and KCA', 'Gifts are handled under the Giving Policy. KCA applications, orientation, and certificates are ministry records; certificates may be publicly verifiable when a verification code is issued. Academic or formation decisions rest with authorised KCA staff.'],
                    ['Disclaimers', 'The platform is provided for ministry use. Teaching, live streams, and publications are offered in good faith but are not a substitute for local pastoral care or professional advice. To the extent permitted by law we are not liable for indirect or consequential loss, and our aggregate liability is limited to the amount of fees you paid us in the three months before the claim (or, if none, a modest fixed amount).'],
                    ['Suspension and changes', 'We may suspend accounts that threaten safety, security, or the integrity of the family. We may update these terms; continued use after notice constitutes acceptance. If a provision is unenforceable, the rest remains in effect. These terms are governed by the laws applicable at our headquarters unless a mandatory local law says otherwise.'],
                ]),
            ],
            [
                'slug' => 'cookies',
                'title' => 'Cookie Policy',
                'summary' => 'Cookies and similar technologies used on the Family House Connect website.',
                'body' => 'This Cookie Policy explains how the Family House Connect website stores small files or similar technologies on your device. Last updated: 8 September 2026.',
                'items' => $this->blocks([
                    ['Essential cookies', 'Required cookies keep you signed in, remember language, protect forms and sessions, and enforce security such as CSRF and multi-factor steps. The site cannot function reliably if these are blocked.'],
                    ['Preferences', 'We may store locale, display, and similar choices so the site stays in the language and layout you selected.'],
                    ['Analytics and media', 'If analytics are enabled, they help us understand which public pages are used so we can improve ministry communication. Embedded live or video players may set their own cookies. We do not use cookies to sell advertising profiles.'],
                    ['Managing cookies', 'You can delete or block cookies in your browser settings. Blocking essential cookies may sign you out or reset language. For personal-data rights, see the Privacy Policy.'],
                ]),
            ],
            [
                'slug' => 'safeguarding',
                'title' => 'Safeguarding',
                'summary' => 'Our commitment to protect children, young people, and vulnerable adults.',
                'body' => 'Family House Connect is committed to a culture of safety in every church, home fellowship, event, KCA cohort, and digital space. Last updated: 8 September 2026.',
                'items' => $this->blocks([
                    ['Our standard', 'Leaders and volunteers who work with children or vulnerable people should be known, trained, and supervised according to local church policy. Digital tools include guardian links, communication restrictions, and restricted safeguarding case handling for authorised staff.'],
                    ['Children online', 'Do not create an account for a child in a way that hides their age. Parents and guardians should supervise device use. We may limit messaging, directory visibility, and live-chat features for minors.'],
                    ['Report a concern', 'If someone is in immediate danger, contact local emergency services first. Then tell a local church leader and, for platform records, use Help & Support or email hello@familyhouseconnect.org with enough detail for the safeguarding team to act. Do not investigate privately or share allegations in public channels.'],
                    ['How we respond', 'Authorised staff may restrict accounts, preserve evidence, inform pastors, and cooperate with statutory authorities. Safeguarding files are confidential and retained as required by law and ministry duty. False or malicious reports may themselves be treated as a community violation.'],
                ]),
            ],
            [
                'slug' => 'community-guidelines',
                'title' => 'Community Guidelines',
                'summary' => 'How we gather as one family in churches, groups, and online spaces.',
                'body' => 'These guidelines apply to comments, groups, live gatherings, messages, testimonies, and other community features on Family House Connect. Last updated: 8 September 2026.',
                'items' => $this->blocks([
                    ['Honour Christ and people', 'Speak with grace. Disagree without contempt. Keep teaching and testimony truthful. Follow the pastoral covering of the church or group you have joined.'],
                    ['Keep the space safe', 'Harassment, hate, threats, spam, scams, impersonation, sexually explicit material, and any exploitation of children are forbidden. Do not share another person’s private prayer or contact details without permission.'],
                    ['Live streams and groups', 'During live services and chats, stay on the message, avoid disruption, and respect hosts. Group leaders may remove posts that break these rules. Repeat harm can lead to muted chat, group removal, or account suspension.'],
                    ['Enforcement', 'We may remove content or restrict access to protect the family. Serious or illegal activity may be reported to authorities. Questions about a moderation decision can be sent through Contact Us.'],
                ]),
            ],
            [
                'slug' => 'giving-policy',
                'title' => 'Giving Policy',
                'summary' => 'Tithes, offerings, missions gifts, and project donations on Family House Connect.',
                'body' => 'Giving through Family House Connect is an act of worship. This policy explains funds, receipts, recurring gifts, and how we handle mistakes. Last updated: 8 September 2026. It is not tax or legal advice; confirm deductibility with your own adviser and local law.',
                'items' => $this->blocks([
                    ['Funds and designation', 'You may give toward tithe, offering, missions, KCA, or a published project. We apply gifts to the fund you select. If a project is fully funded or cannot proceed, remaining amounts may be used for a closely related ministry purpose.'],
                    ['Payments and receipts', 'Payments are processed by our payment partners. After a successful gift, a receipt is available in My Giving History. Keep receipts for your records. Recurring giving can be started or stopped from your account while the mandate remains valid.'],
                    ['Refunds', 'Charitable gifts are generally not refundable once the payment succeeds. Contact hello@familyhouseconnect.org promptly if you were charged twice, charged the wrong amount, or did not intend the payment, and include the receipt reference. Approved refunds, if any, go back to the original payment method.'],
                    ['Integrity', 'Do not use another person’s payment method without authority. We may decline or reverse gifts that appear fraudulent. Giving does not purchase influence, certificates, or membership rights beyond what Scripture and church order already provide.'],
                ]),
            ],
            [
                'slug' => 'beliefs',
                'title' => 'Statement of Faith',
                'summary' => 'The biblical convictions that shape Family House Connect.',
                'body' => 'Family House Connect exists so every believer can find community, grow in Jesus Christ, serve with purpose, and multiply disciples. The following convictions guide our churches, missions, KCA, and publications.',
                'items' => $this->blocks([
                    ['The Bible', 'We believe the Holy Scriptures are the inspired Word of God and the final authority for faith, worship, and life.'],
                    ['God', 'We believe in one God, eternally revealed as Father, Son, and Holy Spirit, worthy of all worship.'],
                    ['Jesus Christ', 'We believe Jesus Christ is the Son of God, born of the virgin Mary, crucified for our sins, risen from the dead, and returning in glory. Salvation is by grace through faith in Him.'],
                    ['The Holy Spirit', 'We believe the Holy Spirit regenerates, empowers, gifts, and sends the Church to witness in word and deed.'],
                    ['The Church', 'We believe the Church is the Body of Christ, expressed in local congregations, home fellowships, and the global family, called to worship, discipleship, fellowship, and mission.'],
                    ['Mission', 'We believe the Great Commission sends us to make disciples of all nations — planting churches, training leaders, showing compassion, and proclaiming the gospel of the Kingdom.'],
                ]),
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $sections
     * @return list<array{kind: string, title: string, body: string}>
     */
    private function blocks(array $sections): array
    {
        $items = [];
        foreach ($sections as $section) {
            $items[] = [
                'kind' => 'block',
                'title' => $section[0],
                'body' => $section[1],
            ];
        }

        return $items;
    }
}
