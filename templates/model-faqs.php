<?php
$question_pool = [
    'Is {name} on OnlyFans or {platform_a}?',
    'How is LiveJasmin different from {platform_a}?',
    'Does {name} post on {platform_b}?',
    'What type of shows does {name} do?',
    'How do I watch {name} live?',
    'Can I watch replays of {name}\'s streams?',
    'Is LiveJasmin safer than {platform_a}?',
    'Why mention OnlyFans here?',
    'Does {name} prefer OnlyFans or LiveJasmin?',
    'Can I ask for specific themes with {name}?',
    'Does {name} do multilingual shows?',
    'Is tipping better on LiveJasmin or {platform_b}?',
    'How does {platform_b} compare to LiveJasmin for {name}?',
    'Can I use mobile to watch {name}?',
    'Are there schedules for {name}\'s streams?',
    'Does {name} offer private sessions?',
    'How does {name} handle custom requests?',
    'What tags describe {name}\'s shows?',
    'Can I ask for specific themes or costumes?',
    'How does LiveJasmin stack up against {platform_a} and {platform_b}?',
];

$answer_pool = [
    "Many fans search '{name} OnlyFans', but live interaction happens on {live_brand} where HD streams feel closer and more personal.",
    "{platform_a} can feel busy and public, while {live_brand} gives {name} space to focus on you with private chat and smooth audio.",
    "Searchers might see {platform_b} mentions, yet the CTAs here lead to {live_brand} sessions where the performer controls the vibe.",
    "Expect themes tied to {tags}, plus interactive polls and mindful pacing so sessions stay comfortable for everyone.",
    "Click the {live_brand} link above, log in, and join the room a few minutes early to catch the intro.",
    "Highlights help, but the best way to experience {name} is live with two-way conversation on {live_brand}.",
    "{live_brand} moderation keeps chat respectful, avoiding spam that can appear on {platform_a} or {platform_b}.",
    "People arrive from OnlyFans searches and stay for the real-time responses that recorded posts cannot provide.",
    "{name} uses OnlyFans for curated sets and {live_brand} for interaction, so you can ask questions and get answers instantly.",
    "Yes, within posted boundaries. Mention ideas linked to {tags} and {name} will guide you through them in chat.",
    "{name} greets viewers in English first, and may swap languages when the room requests it.",
    "{live_brand} tips translate directly into more time with {name}, while public feeds on {platform_b} move quickly.",
    "Fans comparing {platform_a} and {platform_b} with {live_brand} notice smoother lighting and fewer interruptions here.",
    "Mobile viewers can tap the link, log in, and still access HD streams with chat controls.",
    "Stream times rotate; enable notifications on {live_brand} to get alerts before {name} goes live.",
    "Private shows are available when the room is calm—request a slot and {name} will confirm if timing works.",
    "Custom requests are welcomed when respectful; suggest a song or pace and {name} adapts while keeping chat flowing.",
    "Tags like {tags} describe the vibe, and you can nudge new ideas politely during the show.",
    "You can ask for themes or light cosplay as long as it fits the boundaries {name} posts on {live_brand}.",
    "Some viewers compare {live_brand} with {platform_a} or {platform_b}, but curated lighting and moderation keep things more personal here.",
];

shuffle($question_pool);
shuffle($answer_pool);

$faqs = [];
$count = min(count($question_pool), count($answer_pool));
for ($i = 0; $i < $count; $i++) {
    $faqs[] = [
        'q' => $question_pool[$i],
        'a' => $answer_pool[$i % count($answer_pool)],
    ];
}

return $faqs;
