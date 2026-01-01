<?php
/**
 * GOOGLE AUTOCOMPLETE SEEDS - All 30 Categories
 * 
 * Strategy: Google Autocomplete is MORE permissive than Serper
 * We can use semi-explicit seeds that trigger real user autocomplete
 * 
 * Format: Seeds are actual query starters that Google users type
 * Google will auto-complete them with popular searches
 * 
 * Expected Results: 80-200 keywords per category
 * 
 * Usage:
 * Query: "asian cam" → Google returns: ["asian cam girls", "asian cam sites", "asian cam models", ...]
 */

return [
    /**
     * ETHNIC/REGIONAL CATEGORIES
     */
    'asian' => [
        // Direct but safe
        'asian cam',
        'asian webcam',
        'asian live',
        'asian model',
        'asian streaming',
        
        // Geographic specifics
        'japanese cam',
        'korean webcam',
        'thai live',
        'chinese model',
        'filipino cam',
        
        // Combo searches
        'asian webcam sites',
        'asian cam chat',
        'watch asian',
    ],
    
    'latina' => [
        'latina cam',
        'latina webcam',
        'latina live',
        'latina model',
        'latin webcam',
        
        // Country-specific
        'colombian cam',
        'mexican webcam',
        'brazilian live',
        'argentinian model',
        
        // Variations
        'spanish cam',
        'hispanic webcam',
        'latina streaming',
    ],
    
    'ebony' => [
        'ebony cam',
        'ebony webcam',
        'ebony live',
        'ebony model',
        'black cam',
        'black webcam',
        'african cam',
        'ebony streaming',
        'ebony chat',
    ],
    
    'white' => [
        'white cam',
        'white webcam',
        'caucasian cam',
        'european webcam',
        'white model',
        'western cam',
    ],
    
    'interracial' => [
        'interracial cam',
        'mixed webcam',
        'interracial live',
        'diverse cam',
        'multicultural webcam',
    ],
    
    /**
     * PHYSICAL ATTRIBUTES
     */
    'big-boobs' => [
        // Google allows these
        'big boobs cam',
        'big tits webcam',
        'busty cam',
        'busty webcam',
        'big breasts cam',
        'busty model',
        'big boobs live',
        'busty streaming',
    ],
    
    'big-butt' => [
        'big ass cam',
        'big booty webcam',
        'thick cam',
        'thick webcam',
        'big butt live',
        'booty cam',
        'pawg webcam',
    ],
    
    'curvy' => [
        'curvy cam',
        'curvy webcam',
        'curvy model',
        'thick curvy cam',
        'plus size cam',
        'bbw webcam',
        'curvy live',
    ],
    
    'athletic' => [
        'athletic cam',
        'fit webcam',
        'athletic model',
        'fitness cam',
        'athletic girl cam',
        'sporty webcam',
        'fit model cam',
    ],
    
    'petite' => [
        'petite cam',
        'petite webcam',
        'petite model',
        'small cam',
        'petite girl webcam',
        'tiny cam',
        'petite live',
    ],
    
    /**
     * HAIR COLOR
     */
    'blonde' => [
        'blonde cam',
        'blonde webcam',
        'blonde model',
        'blonde live',
        'blonde girl cam',
        'platinum blonde webcam',
        'blonde streaming',
    ],
    
    'brunette' => [
        'brunette cam',
        'brunette webcam',
        'brunette model',
        'dark hair cam',
        'brown hair webcam',
        'brunette live',
    ],
    
    'redhead' => [
        'redhead cam',
        'redhead webcam',
        'redhead model',
        'ginger cam',
        'red hair webcam',
        'redhead live',
    ],
    
    /**
     * PERSONALITY/INTERACTION
     */
    'chatty' => [
        'chatty cam',
        'talkative webcam',
        'chat cam',
        'interactive webcam',
        'friendly cam',
        'chatty model',
    ],
    
    'dominant' => [
        'dominant cam',
        'domme webcam',
        'dominatrix cam',
        'femdom webcam',
        'dominant model',
        'mistress cam',
    ],
    
    'romantic' => [
        'romantic cam',
        'sensual webcam',
        'romantic model',
        'girlfriend cam',
        'intimate webcam',
        'romantic live',
    ],
    
    /**
     * ACTIVITY/THEME
     */
    'cosplay' => [
        'cosplay cam',
        'cosplay webcam',
        'cosplay model',
        'anime cam',
        'costume webcam',
        'cosplay girl cam',
        'cosplay streaming',
    ],
    
    'dance' => [
        'dance cam',
        'dancing webcam',
        'dancer cam',
        'dance model',
        'strip cam',
        'twerk webcam',
    ],
    
    'fitness' => [
        'fitness cam',
        'workout webcam',
        'fitness model cam',
        'gym webcam',
        'fitness girl cam',
        'yoga webcam',
    ],
    
    'glamour' => [
        'glamour cam',
        'glamour model',
        'glamorous webcam',
        'glam cam',
        'luxury webcam',
        'high fashion cam',
    ],
    
    'outdoor' => [
        'outdoor cam',
        'outdoor webcam',
        'public cam',
        'outside webcam',
        'outdoor model',
        'nature cam',
    ],
    
    'roleplay' => [
        'roleplay cam',
        'roleplay webcam',
        'fantasy cam',
        'roleplay model',
        'acting webcam',
    ],
    
    'tattoo-piercing' => [
        'tattoo cam',
        'tattooed webcam',
        'tattoo model',
        'pierced cam',
        'inked webcam',
        'alt cam',
        'alternative webcam',
    ],
    
    'uniforms' => [
        'uniform cam',
        'nurse webcam',
        'schoolgirl cam',
        'maid webcam',
        'secretary cam',
        'costume webcam',
    ],
    
    /**
     * RELATIONSHIP/DYNAMIC
     */
    'couples' => [
        'couples cam',
        'couple webcam',
        'couples live',
        'duo cam',
        'pair webcam',
        'couples streaming',
    ],
    
    /**
     * NICHE/SPECIAL
     */
    'fetish-lite' => [
        'fetish cam',
        'fetish webcam',
        'kink cam',
        'fetish model',
        'specialty cam',
        'alternative webcam',
    ],
    
    /**
     * PLATFORM-SPECIFIC
     */
    'livejasmin' => [
        // Brand queries
        'livejasmin',
        'live jasmin',
        'livejasmin models',
        'livejasmin site',
        'livejasmin vs',
        'livejasmin review',
        'livejasmin free',
        'livejasmin alternative',
        'livejasmin cam',
    ],
    
    'compare-platforms' => [
        // Comparison queries
        'livejasmin vs chaturbate',
        'chaturbate vs stripchat',
        'best cam sites',
        'cam site comparison',
        'cam sites like',
        'chaturbate alternative',
        'stripchat vs',
        'top cam sites',
        'cam sites review',
    ],
    
    /**
     * GENERAL/CATCH-ALL
     */
    'general' => [
        // Broad queries
        'cam',
        'webcam',
        'live cam',
        'cam sites',
        'webcam sites',
        'cam models',
        'live webcam',
        'cam girls',
        'webcam chat',
        'live streaming',
        'cam show',
        'private cam',
        'cam to cam',
        'free cam',
        'live chat',
    ],
];
