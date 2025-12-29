<?php
$bio_openers = [
    "{name} treats each LiveJasmin session like a guided experience, mixing slow pacing with check-ins so newcomers feel welcome.",
    "This performer balances playful jokes with focused storytelling, helping fans escape endless OnlyFans scrolling by delivering live energy.",
    "Community members describe {name} as a thoughtful entertainer who keeps private chats respectful and attentive.",
    "Instead of guessing when a model goes live, viewers rely on {name}'s consistent LiveJasmin schedule and calm updates.",
];

$specialties = [
    "Expect thematic shows inspired by {tags}, weaving those ideas into interactive polls and whispered asides.",
    "Her playlists lean into the vibe of {tags}, ensuring every performance feels curated rather than random.",
    "She references {tags} as inspiration but always keeps the tone approachable for first-time guests.",
    "Tag-driven themes like {tags} guide the flow so each night feels unique without straying into risky claims.",
];

$platform_angles = [
    "While OnlyFans offers on-demand uploads, {name} uses LiveJasmin to deliver real-time reactions and sharper HD quality.",
    "Compared to crowded {platform_a} rooms, her LiveJasmin shows emphasize private attention and clear audio.",
    "Fans who tried {platform_b} note that LiveJasmin keeps distractions low and camera angles steady.",
    "LiveJasmin's moderation tools keep the room comfortable, avoiding the spam that sometimes floods {platform_a} streams.",
    "Regular viewers note that while OnlyFans updates arrive on {name}'s schedule, LiveJasmin sessions respond to the room's energy in real time.",
    "The shift from browsing OnlyFans galleries to joining {name} on LiveJasmin changes the dynamic from passive viewing to active participation.",
    "Fans appreciate how {name} balances OnlyFans content creation with LiveJasmin's live interaction, offering both formats to different audiences.",
    "Unlike OnlyFans where {name} curates finished posts, LiveJasmin shows capture unscripted moments that feel more authentic.",
];

$style_blocks = [
    "She narrates transitions so viewers know when a playful segment shifts into something slower and more intimate.",
    "Lighting shifts from warm gold to soft blues, matching the tempo of her conversation and playful teasing.",
    "She encourages viewers to stretch, hydrate, and settle in, mirroring the mindfulness vibe of the show.",
    "Viewers appreciate how she summarizes chat highlights, making everyone feel acknowledged even in larger crowds.",
];

$schedule = [
    "Most sessions start in the evening EST with bonus weekend pop-ups when the room requests more time.",
    "She typically streams after dinner hours and posts reminders so admirers in other time zones can plan ahead.",
    "Expect late-evening shows with occasional midday check-ins; LiveJasmin alerts keep followers informed.",
    "She posts timing notes before each broadcast and follows through so fans never guess when to log in.",
];

$ctas = [
    "Follow on LiveJasmin to get the next alert and avoid missing her most interactive moments.",
    "Bookmark this page and use the LiveJasmin button above to join when the chat warms up.",
    "Switch from OnlyFans browsing to a LiveJasmin session with {name} for genuine back-and-forth conversation.",
    "Open LiveJasmin in a new tab so you're ready when {name} welcomes the room with fresh energy.",
];

$bios = [];
for ($i = 0; $i < 60; $i++) {
    shuffle($bio_openers);
    shuffle($specialties);
    shuffle($platform_angles);
    shuffle($style_blocks);
    shuffle($schedule);
    shuffle($ctas);
    $sentences = array_merge(
        array_slice($bio_openers, 0, 2),
        array_slice($specialties, 0, 2),
        array_slice($platform_angles, 0, 2),
        array_slice($style_blocks, 0, 2),
        array_slice($schedule, 0, 1),
        array_slice($ctas, 0, 1)
    );
    $bios[] = implode(' ', $sentences);
}

return $bios;
