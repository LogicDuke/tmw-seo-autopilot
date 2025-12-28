<?php
$points = [
    "LiveJasmin streams stay focused on the performer, avoiding the noisy tip floods common on {platform_a}.",
    "OnlyFans delivers on-demand posts, but LiveJasmin lets fans talk to {name} in real time with HD clarity.",
    "{platform_b} public rooms can feel chaotic; LiveJasmin private shows keep attention on genuine interaction.",
    "LiveJasmin quality controls keep lighting and audio stable so every smile and whisper is crisp.",
    "Fans who bounced between {platform_a} and {platform_b} say LiveJasmin's private shows feel more personal.",
    "LiveJasmin replays and highlight reels let viewers revisit favorite moments without waiting for uploads.",
    "Compared to browsing OnlyFans collections, a LiveJasmin chat with {name} feels spontaneous and human.",
    "LiveJasmin moderators keep the vibe respectful, giving {name} room to guide the conversation.",
];

$templates = [];
for ($i = 0; $i < 40; $i++) {
    shuffle($points);
    $body = implode(' ', array_slice($points, 0, 4));
    $templates[] = "<h2>Why Watch {name} on LiveJasmin Instead of OnlyFans or {platform_a}</h2>" . $body;
}
return $templates;
