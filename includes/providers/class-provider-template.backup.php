<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

class Template {
    /** VIDEO: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_video(array $c): array {
        $name = $c['name'];
        $hook = $c['hook'];
        $site = $c['site'];
        $num = 7;
        $title = sprintf('%s — %d Must-See Highlights (Private Show)', $name, $num);
        $meta = sprintf('%s in a clean, quick reel with a direct jump to live chat. Teasers, schedule tips, and links on Top Models Webcam.', $name);
        $keywords = array_merge([$c['focus']], array_slice($c['extras'], 0, 4));

        $lead = sprintf('%s opens this %s collection with two steady beats that feel like a guided tour instead of a teaser.', $name, strtolower($hook));
        $lead .= sprintf(' %s keeps the focus on confident posture and balanced breathing so the first cut already hints at what private show moments can become.', $name);

        $intro_paragraphs = [
            "$name anchors the introduction by narrating what each segment covers, from warm-up glances to the decisive pose that frames the transition into private shows.",
            "With every new angle, $name explains where the reel connects to live chat, highlighting the cues that signal when an exclusive move is about to land.",
            "Scheduling notes land early: evenings lean toward color-rich sets, late nights switch to monochrome silhouettes, and morning drops act as quick check-ins for fans before work.",
            "$name also points to $site resources so viewers know where the teaser ends and the full live experience continues without confusion.",
        ];

        $highlight_paragraphs = [
            "$name spends the first three highlights on tight camera work that shows fingertips, hair, and the soft glow of the set, proving that quiet detail can still feel cinematic.",
            "Midway through, the soundtrack shifts to a mellow beat; $name syncs each gesture with that tempo so the viewer understands how the private show flow will feel.",
            "Chapters six and seven turn into a direct invitation, mixing smiles, slow turns, and whispered prompts that make the countdown to live chat unmistakable.",
            "$name rounds out the highlight reel with a practical reminder to keep notifications on, because pop-up sessions sometimes appear without warning.",
        ];

        $rhythm_paragraphs = [
            "Every transition uses a gentle dissolve so the energy never drops; even between scenes $name keeps eye contact with the camera to maintain momentum.",
            "Lighting cues stay simple: a cool wash for calm beats, a warmer tone for crescendo moments, and subtle shadows whenever the reel needs intrigue.",
            "$name rehearsed each chapter with a focus on breathing, so the pacing mirrors the kind of controlled confidence fans love in private rooms.",
        ];

        $prep_paragraphs = [
            "To prep for live chat, $name recommends loading playlists, setting room lights to match the reel, and joining a minute before the posted schedule for surprise opener moves.",
            "Community notes call out regulars who drop kind tips in chat; those shout-outs keep the vibe warm and encourage newcomers to say hello.",
            "After the final highlight, $name points everyone back to the profile page for photos, wardrobe polls, and an archive of previous teasers that inspire the next session.",
        ];

        $blocks = [
            ['p', $lead],
            ['raw', $this->mini_toc()],
            ['h2', 'Intro', ['id' => 'intro']],
        ];
        foreach ($intro_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        $blocks[] = ['h2', 'Highlights', ['id' => 'highlights']];
        foreach ($highlight_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        if (!empty($c['model_url'])) {
            $blocks[] = ['raw', '<p>Read more about ' . esc_html($name) . ' on the <a href="' . esc_url($c['model_url']) . '">full profile</a> to see photos, notes, and schedule updates.</p>'];
        }
        $blocks[] = ['h2', 'Rhythm & Structure'];
        foreach ($rhythm_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        $blocks[] = ['h2', 'Live Chat Prep'];
        foreach ($prep_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        if (!empty($c['brand_url'])) {
            $blocks[] = ['raw', '<p class="tmwseo-inline-cta"><a href="' . esc_url($c['brand_url']) . '" rel="sponsored nofollow noopener" target="_blank">Jump into ' . esc_html($name) . ' live chat</a> whenever the highlights spark a mood shift.</p>'];
        }

        $faq = [
            ["When is {$name} usually online?", 'Most evenings with occasional weekend sessions; check profile notes.'],
            ["What’s in this reel?", 'A sequence of seven chapters mixing close-ups, pacing notes, and chat-ready cues.'],
            ["How do I join live chat?", 'Use the “Join ' . $name . ' live chat” button or the inline link on this page.'],
            ["Is there more content?", 'Yes—visit the model profile for photos, schedule notes, wardrobe polls, and teasers.'],
        ];
        $blocks[] = ['h2', "$name — FAQ", ['id' => 'faq']];
        $blocks = array_merge($blocks, $this->faq_html($faq));

        $content = $this->html($blocks);
        $content = $this->enforce_word_goal($content, $name);
        $content = $this->apply_density_guard($content, $name);

        return ['title' => $title, 'meta' => $meta, 'keywords' => $keywords, 'content' => $content];
    }

    /** MODEL: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_model(array $c): array {
        $name = $c['name'];
        $site = $c['site'];
        $title = sprintf('%s — Live Chat & Profile', $name);
        $meta = sprintf('%s on %s. Photos, schedule tips, and live chat links. Follow %s for updates and teasers.', $name, $site, $name);
        $keywords = array_merge([$c['focus']], array_slice($c['extras'], 0, 4));

        $lead = sprintf('%s introduces this profile with a calm voiceover that outlines live chat themes and the personal touches fans notice first.', $name);
        $lead .= sprintf(' %s keeps things conversational so the first paragraph already feels like a friendly DM.', $name);

        $intro_paragraphs = [
            "$name mixes editorial-inspired photos with studio snapshots that show how the lighting changes between public and private rooms.",
            "The intro also lists favorite playlists, warm-up rituals, and the subtle cues that signal when a surprise session might pop up.",
            "$name emphasizes community guidelines that keep the profile welcoming, highlighting how respectful vibes lead to more adventurous shows.",
        ];

        $highlight_paragraphs = [
            "$name keeps the highlights section organized by mood: chill mornings, bold evening looks, and limited seasonal shoots that only appear once per quarter.",
            "Each gallery includes notes about wardrobe, camera gear, and post-production choices so aspiring creators can learn from the workflow.",
            "$name also drops little anecdotes about memorable fans, emphasizing how genuine chats inspire the next shoot.",
            "Schedule blocks show when $name prefers structured shows versus spontaneous drop-ins, giving subscribers a practical planning tool.",
        ];

        $community_paragraphs = [
            "Community tips remind followers to bookmark the page, enable notifications, and vote in polls that decide future looks.",
            "$name shares gratitude posts after each milestone, celebrating moderators, gifters, and lurkers who keep the vibe balanced.",
            "A quick rundown of backstage routines—stretching, lighting tests, playlist swaps—helps fans appreciate the craft behind each live chat.",
        ];

        $blocks = [
            ['p', $lead],
            ['raw', $this->mini_toc()],
            ['h2', 'Intro', ['id' => 'intro']],
        ];
        foreach ($intro_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        $blocks[] = ['h2', 'Highlights', ['id' => 'highlights']];
        foreach ($highlight_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        $blocks[] = ['h2', 'Community & Tips'];
        foreach ($community_paragraphs as $p) {
            $blocks[] = ['p', $p];
        }
        if (!empty($c['brand_url'])) {
            $blocks[] = ['raw', '<p class="tmwseo-inline-cta"><a href="' . esc_url($c['brand_url']) . '" rel="sponsored nofollow noopener" target="_blank">Chat live with ' . esc_html($name) . '</a> whenever the profile updates inspire you.</p>'];
        }
        $faq = [
            ["About $name", "$name keeps the layout clean so you can find highlights, new posts, and live times quickly."],
            ["How often is $name live?", 'Most evenings; check the banner note for changes.'],
            ['How to support ' . $name . '?', 'Join live chat, bookmark the profile, leave kind feedback, and share favorite posts.'],
            ['Where to see teasers?', 'Scroll to the media section for short clips, wardrobe polls, and behind-the-scenes snapshots.'],
        ];
        $blocks[] = ['h2', "$name — FAQ", ['id' => 'faq']];
        $blocks = array_merge($blocks, $this->faq_html($faq));

        $content = $this->html($blocks);
        $content = $this->enforce_word_goal($content, $name);
        $content = $this->apply_density_guard($content, $name);

        return ['title' => $title, 'meta' => $meta, 'keywords' => $keywords, 'content' => $content];
    }

    /* helpers */
    protected function html(array $blocks): string {
        $out = '';
        foreach ($blocks as $b) {
            $tag = $b[0];
            $txt = $b[1] ?? '';
            $attrs = $b[2] ?? [];
            if ($tag === 'raw') {
                $out .= $txt;
                continue;
            }
            $attr_html = '';
            foreach ($attrs as $k => $v) {
                $attr_html .= ' ' . $k . '="' . esc_attr($v) . '"';
            }
            if ($tag === 'p') {
                $out .= '<p' . $attr_html . '>' . esc_html($txt) . '</p>';
            } elseif (in_array($tag, ['h2', 'h3'], true)) {
                $out .= '<' . $tag . $attr_html . '>' . esc_html($txt) . '</' . $tag . '>';
            }
        }
        return $out;
    }

    protected function faq_html(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['h3', $r[0]];
            $out[] = ['p', $r[1]];
        }
        return $out;
    }

    protected function mini_toc(): string {
        return '<nav class="tmw-mini-toc">
  <a href="#intro">Intro</a> · <a href="#highlights">Highlights</a> · <a href="#faq">FAQ</a>
</nav>';
    }

    protected function enforce_word_goal(string $content, string $focus, int $min = 900, int $max = 1200): string {
        $words = str_word_count(wp_strip_all_tags($content));
        if ($words >= $min && $words <= $max) {
            return $content;
        }
        $extras = [
            "$focus keeps refining transitions so every new release feels polished without losing spontaneity. The added structure highlights micro-expressions, light changes, and wardrobe details that would be lost in a shorter teaser, helping fans imagine the private room experience.",
            "Fans mention that $focus writes thoughtful captions filled with scene notes, playlists, and gratitude for community members. Those longer updates turn the archive into a true journal, so scrolling back through the feed feels like reading a behind-the-scenes storyboard.",
            "$focus rotates between cinematic color palettes and minimalist backdrops to keep the feed feeling fresh. That experimentation teaches viewers how lighting affects mood, and it reminds everyone that the next live show could surprise them with a brand-new creative direction.",
        ];
        $i = 0;
        while ($words < $min && $i < 20) {
            $addition = '<p>' . esc_html($extras[$i % count($extras)]) . '</p>';
            $next_words = str_word_count(wp_strip_all_tags($content . $addition));
            if ($next_words > $max) {
                break;
            }
            $content .= $addition;
            $words = $next_words;
            $i++;
        }
        return $content;
    }

    protected function apply_density_guard(string $content, string $focus): string {
        $count = substr_count(strtolower($content), strtolower($focus));
        if ($count >= 8) {
            return $content;
        }
        $extras = [
            "$focus leans into natural pauses so each fan can breathe before the next reveal.",
            "When scenes get intense, $focus steadies the camera to keep everything intentional.",
            "$focus closes every update with gratitude, keeping the tone supportive and warm.",
        ];
        $i = 0;
        while ($count < 8 && $i < 10) {
            $content .= '<p>' . esc_html($extras[$i % count($extras)]) . '</p>';
            $count++;
            $i++;
        }
        return $content;
    }
}
