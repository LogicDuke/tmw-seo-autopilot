<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

use TMW_SEO\Core;

class VideoTemplate {
    /** VIDEO: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_video(array $c): array {
        $name   = $c['name'];
        $site   = $c['site'] ?: 'Live Cam Stream';
        $focus  = trim($c['focus'] ?? Core::video_focus($name));
        $focus  = $focus !== '' ? $focus : $name;

        $fallback_extras = [
            'live cam energy',
            'friendly chat stream',
            'studio highlight reel',
            'slow groove moments',
        ];
        $raw_extras = array_map('trim', $c['extras'] ?? []);
        $extras     = array_slice(array_merge($raw_extras, $fallback_extras), 0, 4);
        $keywords   = array_merge([$focus], $extras);

        $number        = 5;
        $sentiment     = 'Calm';
        $power_word    = 'Prime';
        $title_suffix  = sprintf(' — %d %s %s Highlights', $number, $sentiment, $power_word);
        $title         = $focus . $title_suffix;
        if (mb_strlen($title) > 60) {
            $available = max(10, 60 - mb_strlen($title_suffix));
            $trimmed   = rtrim(mb_substr($focus, 0, $available));
            $title     = $trimmed . $title_suffix;
        }

        $meta = sprintf(
            '%s shares mellow tales as %s, %s, %s, and %s guide a cozy flow.',
            $focus,
            $extras[0],
            $extras[1],
            $extras[2],
            $extras[3]
        );
        if (mb_strlen($meta) > 160) {
            $meta = mb_substr($meta, 0, 157) . '...';
        }

        $blocks = [];
        $intro_one = sprintf(
            '%s opens the stream with a mellow greeting, describing how the evening will follow a relaxed curve so viewers can ease into each scene with steady breathing. Conversations about %s float through the room like a warm breeze, hinting at the kind of playful energy that still feels mindful and respectful. Observers are told to settle into their seats, feel the cushions under their palms, and notice how the host uses long pauses to encourage a calm rhythm while promising gentle surprises. She invites watchers to imagine soft velvet drapes swaying at the edge of the frame, helping them picture themselves inside the story before the first highlight begins.',
            $focus,
            $extras[0]
        );
        $intro_two = sprintf(
            'Guides explain that %s keeps a soft gaze on each viewer, pausing between statements so the chat can breathe and respond without rush. The narrator invites everyone to imagine how %s will frame the conversation later in the night, balancing kind jokes with gentle check-ins. People are reminded to sip water, stretch wrists, and let their own stories surface before the next wave of smiles arrives. Extra encouragement suggests making a short playlist of calming songs to play quietly at home so the mood of the stream feels even more immersive.',
            $focus,
            $extras[1]
        );
        $intro_two .= sprintf(' Preview whispers already point toward %s gliding into a reflective segment, giving fans a hint of the calm choreography to come.', $extras[2]);

        $blocks[] = ['p', $intro_one];
        $blocks[] = ['p', $intro_two];
        $intro_extra_three = sprintf('Preview whispers about %s tonight.', $extras[2]);
        $intro_extra_four  = sprintf('Another hint says %s will glow.', $extras[3]);
        $blocks[] = ['raw', '<div class="intro-note">' . esc_html($intro_extra_three) . '</div>'];
        $blocks[] = ['raw', '<div class="intro-note">' . esc_html($intro_extra_four) . '</div>'];

        $blocks[] = ['h2', $extras[0]];
        $section1_p1 = sprintf(
            'Hosts describe how %s becomes a playful current that guides viewers through the first arc, with descriptive language showing how each nod and soft laugh carries a ripple of anticipation. They paint the moment as a flowing river of grins, encouraging the audience to follow the pace by matching the rise of their shoulders with the storyteller\'s relaxed breathing. Anecdotes from longtime fans emphasize how the earliest beats are ideal for setting intentions about kindness and patience.',
            $extras[0]
        );
        $section1_p2 = 'Commentators linger on the way the opening segment stretches time, noting how gentle gestures encourage viewers to hum along quietly in their living rooms. Tips suggest holding a favorite cushion, noticing the texture, and letting those sensations tie them to the unfolding scene. A reminder to dim overhead lights and rely on small lamps helps keep the atmosphere tender and unrushed.';
        $section1_p2 .= sprintf(' Storytellers highlight how %s keeps that softness anchored, making the early minutes feel like a familiar companion guiding the room.', $extras[0]);
        $section1_p3 = 'By the end of the section, watchers feel like they have been carried through a calm dance, thanks to steady pacing and heartfelt chat prompts that ask for single-word reflections. Observers jot those words in a notebook so they can revisit the feelings later, reinforcing the sense of communal mindfulness.';
        $section1_p3 .= sprintf(' Gentle callbacks to %s remind them that the relaxed current can be summoned whenever they replay the highlight reel.', $extras[0]);
        $blocks[] = ['p', $section1_p1];
        $blocks[] = ['p', $section1_p2];
        $blocks[] = ['p', $section1_p3];

        $blocks[] = ['h2', $extras[1]];
        $section2_p1 = sprintf(
            'Writers say %s transforms the shared space into a lounge where compliments arrive slowly and laughter stays airy. They urge participants to type a single uplifting phrase, then set their hands down to absorb the hush that follows. The advice mentions that pauses are not empty; they are invitations to feel grounded and appreciative.',
            $extras[1]
        );
        $section2_p2 = sprintf(
            'Moderators explain that %s reacts to each message with deliberate pauses, tilting their head and letting a thoughtful grin grow before the next topic. This pacing teaches fans to value silence as much as conversation, making every response feel personal. The guidance encourages everyone to leave a beat of quiet after each compliment so the appreciation can truly land.',
            $focus
        );
        $section2_p2 .= sprintf(' In doing so, the room senses how %s can keep a live exchange cheerful even when everyone chooses to type less and listen more.', $extras[1]);
        $section2_p3 = 'Attendees are coached to mirror that pace by counting heartbeats between their own comments, letting curiosity replace urgency so the discussion maintains a gentle sway.';
        $section2_p3 .= sprintf(' That mindful rhythm makes %s feel like a shared promise to protect the cozy lounge vibe all night.', $extras[1]);
        $blocks[] = ['p', $section2_p1];
        $blocks[] = ['p', $section2_p2];
        $blocks[] = ['p', $section2_p3];

        $blocks[] = ['h2', $extras[2]];
        $section3_p1 = sprintf(
            'Curators compare %s to a late-evening walk along a coastal boardwalk, noting how every shoulder roll lines up with the rhythm of distant waves. They describe shimmering colors that travel across the backdrop like dusk lighting, suggesting viewers imagine the scent of saltwater as they watch. The narrative reminds people to relax their jaw and let their eyes follow the imaginary shoreline from one side of the screen to the other.',
            $extras[2]
        );
        $section3_p2 = sprintf(
            'They describe how %s keeps movements unhurried so watchers can trace each motion with their fingertips resting lightly on their lap, turning the experience into a guided meditation. Slow arcs of the arms invite audiences to match their breathing to a soft four-count, reinforcing calm focus. Observers are nudged to picture silver moonlight skimming over calm water to deepen the immersive feeling.',
            $focus
        );
        $section3_p2 .= sprintf(' Listeners whisper that %s turns every pause into a glimmering snapshot, making the leisurely pace feel intentional and artful.', $extras[2]);
        $section3_p3 = 'Writers encourage viewers to jot down a single word that captures how the segment made them feel, building a small journal of moods that can be revisited whenever calm is needed. That journal becomes a treasure map for future evenings, reminding them how easily serenity can return.';
        $blocks[] = ['p', $section3_p1];
        $blocks[] = ['p', $section3_p2];
        $blocks[] = ['p', $section3_p3];

        $blocks[] = ['h2', $extras[3]];
        $section4_p1 = 'Narrators portray the closing swell as the moment when time stretches, asking viewers to dim nearby lamps and let the hush of the room settle over their shoulders. They speak about the hush as a weighted blanket of comfort, inviting everyone to close their eyes for a heartbeat before lifting them to catch the next smile. Gentle reminders to keep phones facedown help protect the fragile mood.';
        $section4_p1 .= sprintf(' Those final glimmers of %s give the finale a polished sheen, helping fans remember the warmth long after the last frame fades.', $extras[3]);
        $section4_p2 = 'Tips encourage fans to sip something warm, press their heels into the floor, and notice how their breathing steadies when they mirror the presenter\'s gentle posture. The scene is treated like a guided relaxation session, full of soft humming and quiet smiles from the chat.';
        $section4_p2 .= sprintf(' Even the lighting cues lean into %s, with each soft angle accentuating the graceful goodbye.', $extras[3]);
        $section4_p3 = 'By treating the final stretch as a lullaby for the senses, the coverage shows how gratitude becomes the natural closing note, leaving the audience refreshed for the rest of the night. The final moments feel like a handwritten letter of thanks, gentle and sincere.';
        $blocks[] = ['p', $section4_p1];
        $blocks[] = ['p', $section4_p2];
        $blocks[] = ['p', $section4_p3];

        $blocks[] = ['h3', 'Further Viewing'];
        $model_url = !empty($c['model_url']) ? $c['model_url'] : '#';
        $internal_link = sprintf(
            'Keep up with %s by visiting <a href="%s">%s</a>, where %s moments are gathered with caring notes for future watch parties.',
            esc_html($focus),
            esc_url($model_url),
            esc_html($name),
            esc_html($extras[3])
        );
        $blocks[] = ['raw', '<p class="internal-link">' . $internal_link . '</p>'];

        $blocks[] = ['h2', 'Gentle Farewell'];
        $conclusion = sprintf(
            'The final reflection thanks viewers for breathing slowly with %s, encouraging them to carry the mellow tone into the rest of their evening and return when they crave another soothing narrative.',
            $focus
        );
        $blocks[] = ['p', $conclusion];

        $content = $this->html($blocks);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < 800 || $word_count > 1000) {
            $content = $this->pad_word_count($content, $blocks, $word_count);
        }

        return [
            'title'    => $title,
            'meta'     => $meta,
            'keywords' => $keywords,
            'content'  => $content,
        ];
    }

    protected function pad_word_count(string $content, array $blocks, int $word_count): string {
        if ($word_count >= 800 && $word_count <= 1000) {
            return $content;
        }
        $extras = [
            'A reminder panel encourages viewers to stretch ankles, roll shoulders, and let the quiet hum of the soundtrack linger before pressing play again.',
            'Another gentle sidebar suggests lighting a small candle or opening a window so the fresh air anchors the emotional pace of the story.',
        ];
        foreach ($extras as $extra) {
            if ($word_count >= 800) {
                break;
            }
            $blocks[] = ['p', $extra];
            $content   = $this->html($blocks);
            $word_count = str_word_count(strip_tags($content));
        }
        return $content;
    }

    /* helpers */
    protected function html(array $blocks): string {
        $out = '';
        foreach ($blocks as $b) {
            $tag   = $b[0];
            $txt   = $b[1] ?? '';
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
        // Accept whatever the base template produces; no padding.
        return $content;
    }

    protected function apply_density_guard(string $content, string $focus): string {
        // Leave content as-is so keyword density stays moderate
        // and we avoid over-optimization.
        return $content;
    }
}
