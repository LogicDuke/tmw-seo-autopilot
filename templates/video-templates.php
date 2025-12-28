<?php
$intro_hooks = [
    "Fans searching '{name} OnlyFans video' end up here because this LiveJasmin recording shows real interaction instead of static uploads.",
    "People typing '{name} {platform_a} recording' discover how LiveJasmin keeps the camera steady and the conversation flowing.",
    "Those looking for '{name} highlights' will find this recap proves why LiveJasmin private sessions beat public rooms.",
    "This five-minute reel captures the intimacy that OnlyFans clips miss, with LiveJasmin audio that feels present.",
];

$video_details = [
    "Duration: five-minute highlight pulled from a recent LiveJasmin session where {name} answered live requests.",
    "Quality: filmed in 1080p with stable lighting so every smile and hand gesture is crisp.",
    "Categories: {tags}, blended into a comfortable pace that avoids harsh cuts.",
    "Viewers hear the difference: quiet background, clear whispers, and no noisy tip alerts like on {platform_b}.",
];

$engagement = [
    "The clip shows how she asks viewers for mood checks before changing songs, something you cannot feel on OnlyFans.",
    "Live chat reactions guide her pacing, letting shy fans type questions that she answers in real time.",
    "She offers quick reminders to hydrate and stretch, matching the mindful tone of her LiveJasmin room.",
    "Expect playful commentary and calm smiles rather than chaotic public chat; this recording highlights that care.",
];

$cta = [
    "Watch the full show by joining LiveJasmin now and ask {name} for a fresh request.",
    "Open LiveJasmin to experience her next stream live and compare it to any OnlyFans clip you've seen.",
    "Click through to LiveJasmin, follow {name}, and get an alert when the next private room opens.",
];

$templates = [];
for ($i = 0; $i < 40; $i++) {
    shuffle($intro_hooks);
    shuffle($video_details);
    shuffle($engagement);
    shuffle($cta);
    $templates[] = implode(' ', array_merge(
        array_slice($intro_hooks, 0, 1),
        array_slice($video_details, 0, 3),
        array_slice($engagement, 0, 2),
        array_slice($cta, 0, 1)
    ));
}
return $templates;
