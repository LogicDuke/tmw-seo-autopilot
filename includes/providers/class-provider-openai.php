<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

class OpenAI {
    public static function is_enabled(): bool {
        return defined('TMW_SEO_OPENAI') || defined('OPENAI_API_KEY');
    }
    private function api_key(): string {
        return defined('TMW_SEO_OPENAI') ? TMW_SEO_OPENAI : (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
    }

    public function generate_video(array $ctx): array {
        $raw = $this->generate([
            'name' => $ctx['name'],
            'site' => $ctx['site'],
            'primary' => $ctx['hook'],
            'looks' => $ctx['looks'],
        ], 'video');
        if (empty($raw['title'])) {
            return (new Template())->generate_video($ctx);
        }
        return [
            'title' => $raw['title'],
            'meta' => $raw['meta'],
            'keywords' => array_merge([$ctx['focus']], array_slice($ctx['extras'], 0, 4)),
            'content' => $raw['content'],
        ];
    }

    public function generate_model(array $ctx): array {
        $raw = $this->generate([
            'name' => $ctx['name'],
            'site' => $ctx['site'],
            'primary' => $ctx['focus'],
            'looks' => $ctx['looks'],
        ], 'model');
        if (empty($raw['title'])) {
            return (new Template())->generate_model($ctx);
        }
        return [
            'title' => $raw['title'],
            'meta' => $raw['meta'],
            'keywords' => array_merge([$ctx['focus']], array_slice($ctx['extras'], 0, 4)),
            'content' => $raw['content'],
        ];
    }

    public function generate(array $ctx, string $type = 'model'): array {
        if (!$this->api_key()) {
            return [];
        }
        if ($type === 'model') {
            $prompt = sprintf(
                "Write long-form, non-explicit SEO content for a %s page on an adult-friendly webcam directory.\n".
                "Name (main focus keyword): %s\n".
                "Site: %s\n".
                "Primary look or theme: %s\n".
                "Other looks or tags: %s\n\n".
                "Goals:\n".
                "- Produce helpful, human-readable content that could appear on a profile page.\n".
                "- Use the name as the main focus keyword with an approximate density of 1.0–1.5%%.\n".
                "- Do not use explicit sexual words or graphic descriptions. Keep the tone safe, friendly, and PG-13.\n\n".
                "Return STRICT JSON (no markdown, no commentary) with the keys: title, meta, focus, content.\n".
                "- title: SEO title (max ~60 characters) starting with the name. Include at least one power word or a number when natural.\n".
                "- meta: SEO meta description (140–160 characters) including the name once and summarizing the profile (photos, schedule tips, live chat, etc.).\n".
                "- focus: an array of 3–6 focus keywords where the first item is exactly the name, and others are short search phrases like \"{name} live cam\", \"{name} profile\", \"live cam model\", etc. Avoid explicit words.\n".
                "- content: HTML, 850–1100 words, using <p> and <h2> tags only.\n\n".
                "Content structure for the HTML:\n".
                "- Start with one short <p> intro paragraph that mentions the name and what visitors can expect.\n".
                "- Then include these sections, each with an <h2> and 1–3 <p> paragraphs:\n".
                "  1) <h2>Intro</h2>\n".
                "  2) <h2>About %s</h2>\n".
                "  3) <h2>Streaming Style & What to Expect</h2>\n".
                "  4) <h2>Schedule Notes</h2>\n".
                "  5) <h2>Community & Tips</h2>\n".
                "  6) <h2>FAQ</h2>\n".
                "- Make sure the name appears naturally across the sections, but never in an obviously spammy way.\n".
                "- Emphasize live chat, schedule patterns, viewer experience, and community, but stay non-explicit.",
                $type,
                $ctx['name'],
                $ctx['site'],
                $ctx['primary'],
                implode(', ', (array) $ctx['looks']),
                $ctx['name']
            );
        } else {
            // Fallback for videos or other types: keep concise 300–500 word SEO content
            $prompt = sprintf(
                "Write concise SEO content for a %s page.\n".
                "Name: %s\n".
                "Site: %s\n".
                "Primary look or theme: %s\n".
                "Other looks or tags: %s\n\n".
                "Return STRICT JSON with the keys: title, meta, focus, content.\n".
                "- title: SEO title (max ~60 characters).\n".
                "- meta: SEO meta description (140–160 characters).\n".
                "- focus: a short array of focus keywords.\n".
                "- content: HTML, 300–500 words with a few <h2> sections and <p> paragraphs.",
                $type,
                $ctx['name'],
                $ctx['site'],
                $ctx['primary'],
                implode(', ', (array) $ctx['looks'])
            );
        }

        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key(),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 25,
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a concise SEO writer. Keep content safe for work.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.5,
            ]),
        ]);

        if (is_wp_error($res)) {
            error_log('[TMW-SEO-GEN] OpenAI error: ' . $res->get_error_message());
            return [];
        }

        $json = json_decode(wp_remote_retrieve_body($res), true);
        $text = $json['choices'][0]['message']['content'] ?? '';
        $payload = json_decode($text, true);
        if (!is_array($payload) || empty($payload['title'])) {
            return [];
        }
        $payload['title'] = sanitize_text_field($payload['title']);
        $payload['meta']  = sanitize_text_field($payload['meta']);
        $payload['focus'] = array_map('sanitize_text_field', (array)$payload['focus']);
        $payload['content'] = wp_kses_post($payload['content']);

        return $payload;
    }
}
