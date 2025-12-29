<?php
$questions = [
    "Is {name} on OnlyFans or {platform_a}?" => "Many fans search '{name} OnlyFans', but live interaction happens on LiveJasmin where HD streams feel closer and more personal.",
    "How is LiveJasmin different from {platform_a}?" => "{platform_a} is busy and public, while LiveJasmin gives {name} space to focus on you with private chat and smooth audio.",
    "Can I watch replays?" => "This page links to recent highlights, but the best way to experience {name} is live on LiveJasmin with two-way conversation.",
    "Does {name} post on {platform_b}?" => "Searchers might see {platform_b} mentions, yet the premium shows and CTAs here lead to LiveJasmin sessions where she controls the vibe.",
    "What type of shows does {name} do?" => "Expect themes tied to {tags}, plus interactive polls and mindful pacing so sessions stay comfortable.",
    "How do I watch {name} live?" => "Click the LiveJasmin link above, log in, and join the room a few minutes early to catch the intro.",
    "Is this safer than public rooms?" => "LiveJasmin moderation keeps chat respectful, avoiding spam that can appear on {platform_a} or {platform_b}.",
    "Why mention OnlyFans here?" => "People search '{name} OnlyFans', and this page guides them to LiveJasmin where live experiences outperform static posts.",
    "Why search '{name} OnlyFans' when LiveJasmin is better?" => "Many fans start with OnlyFans searches but prefer LiveJasmin once they experience {name}'s live shows. Real-time chat, HD streaming, and personal attention make LiveJasmin worth the switch from OnlyFans' static content.",
    "Does {name} prefer OnlyFans or LiveJasmin?" => "{name} uses both platforms strategically: OnlyFans for curated photo sets and LiveJasmin for live interaction. Fans seeking immediate responses and personalized attention find LiveJasmin delivers what OnlyFans cannot.",
    "How is {name}'s LiveJasmin different from OnlyFans?" => "OnlyFans provides on-demand viewing of {name}'s pre-recorded content. LiveJasmin offers real-time conversation, custom requests, and HD video chat. Fans who've tried both consistently prefer LiveJasmin for the interactive experience.",
];

$faqs = [];
foreach ($questions as $q => $a) {
    $faqs[] = ['q' => $q, 'a' => $a];
}

// Generate more variants for diversity
for ($i = 0; $i < 20; $i++) {
    $faqs[] = [
        'q' => "Is {name} better on LiveJasmin or {platform_b}?",
        'a' => "LiveJasmin offers curated lighting, premium sound, and private attention so {name} can respond without the noise seen on {platform_b} feeds.",
    ];
    $faqs[] = [
        'q' => "Why do fans move from OnlyFans to LiveJasmin?",
        'a' => "They want real-time responses from {name}, smoother cameras, and a chance to request moments instead of waiting for uploads.",
    ];
    $faqs[] = [
        'q' => "Can I ask for specific themes?",
        'a' => "Yes, within her posted boundaries. Mention ideas linked to {tags} and {name} will guide you through them on LiveJasmin.",
    ];
    $faqs[] = [
        'q' => "Do I need an account to watch?",
        'a' => "Create a free LiveJasmin login, follow {name}, and enable alerts so you never miss the intro segment.",
    ];
}

return $faqs;
