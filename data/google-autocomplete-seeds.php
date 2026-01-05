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
        'asian cam girl',
        'asian cam model',
        'asian live cam',
        'asian webcam model',
        'asian cam chat',
        'asian cam sites',
        
        // Geographic specifics
        'japanese cam girl',
        'korean webcam model',
        'thai live cam',
        'chinese cam model',
        'filipino cam girl',
        
        // Combo searches
        'asian webcam sites',
        'asian cam chat',
    ],
    
    'latina' => [
        'latina cam girl',
        'latina cam model',
        'latina live cam',
        'latina webcam model',
        'latin cam model',
        
        // Country-specific
        'colombian cam girl',
        'mexican webcam model',
        'brazilian live cam',
        'argentinian cam model',
        
        // Variations
        'spanish cam girl',
        'hispanic webcam model',
    ],
    
    'ebony' => [
        'ebony cam girl',
        'ebony cam model',
        'ebony live cam',
        'ebony webcam model',
        'black cam girl',
        'black cam model',
        'african cam girl',
        'ebony cam chat',
    ],
    
    'white' => [
        'white cam girl',
        'white webcam model',
        'caucasian cam girl',
        'european webcam model',
        'white cam model',
        'western cam girl',
    ],
    
    'interracial' => [
        'interracial cam model',
        'mixed webcam model',
        'interracial live cam',
        'diverse cam girl',
        'multicultural webcam model',
    ],
    
    /**
     * PHYSICAL ATTRIBUTES
     */
    'big-boobs' => [
        // Google allows these
        'big boobs cam girl',
        'big tits webcam model',
        'busty cam girl',
        'busty webcam model',
        'big breasts cam model',
        'busty cam model',
        'big boobs live cam',
    ],
    
    'big-butt' => [
        'big ass cam girl',
        'big booty webcam model',
        'thick cam girl',
        'thick webcam model',
        'big butt live cam',
        'booty cam model',
        'pawg webcam model',
    ],
    
    'curvy' => [
        'curvy cam girl',
        'curvy webcam model',
        'curvy cam model',
        'thick curvy cam girl',
        'plus size cam model',
        'bbw webcam model',
        'curvy live cam',
    ],
    
    'athletic' => [
        'athletic cam girl',
        'fit webcam model',
        'athletic cam model',
        'fitness cam girl',
        'athletic girl cam',
        'sporty webcam model',
        'fit cam model',
    ],
    
    'petite' => [
        'petite cam girl',
        'petite webcam model',
        'petite cam model',
        'small cam girl',
        'petite girl webcam model',
        'tiny cam girl',
        'petite live cam',
    ],
    
    /**
     * HAIR COLOR
     */
    'blonde' => [
        'blonde cam girl',
        'blonde webcam model',
        'blonde cam model',
        'blonde live cam',
        'blonde girl cam',
        'platinum blonde webcam model',
    ],
    
    'brunette' => [
        'brunette cam girl',
        'brunette webcam model',
        'brunette cam model',
        'dark hair cam girl',
        'brown hair webcam model',
        'brunette live cam',
    ],
    
    'redhead' => [
        'redhead cam girl',
        'redhead webcam model',
        'redhead cam model',
        'ginger cam girl',
        'red hair webcam model',
        'redhead live cam',
    ],
    
    /**
     * PERSONALITY/INTERACTION
     */
    'chatty' => [
        'chatty cam girl',
        'talkative webcam model',
        'chat cam girl',
        'interactive webcam model',
        'friendly cam girl',
        'chatty cam model',
    ],
    
    'dominant' => [
        'dominant cam girl',
        'domme webcam model',
        'dominatrix cam girl',
        'femdom webcam model',
        'dominant cam model',
        'mistress cam girl',
    ],
    
    'romantic' => [
        'romantic cam girl',
        'sensual webcam model',
        'romantic cam model',
        'girlfriend cam girl',
        'intimate webcam model',
        'romantic live cam',
    ],
    
    /**
     * ACTIVITY/THEME
     */
    'cosplay' => [
        'cosplay cam girl',
        'cosplay webcam model',
        'cosplay cam model',
        'anime cam girl',
        'costume webcam model',
        'cosplay girl cam',
    ],
    
    'dance' => [
        'dance cam girl',
        'dancing webcam model',
        'dancer cam girl',
        'dance cam model',
        'strip cam girl',
        'twerk webcam model',
    ],
    
    'fitness' => [
        'fitness cam girl',
        'workout webcam model',
        'fitness cam model',
        'gym webcam model',
        'fitness girl cam',
        'yoga webcam model',
    ],
    
    'glamour' => [
        'glamour cam girl',
        'glamour cam model',
        'glamorous webcam model',
        'glam cam girl',
        'luxury webcam model',
        'high fashion cam girl',
    ],
    
    'outdoor' => [
        'outdoor cam girl',
        'outdoor webcam model',
        'public cam girl',
        'outside webcam model',
        'outdoor cam model',
        'nature cam girl',
    ],
    
    'roleplay' => [
        'roleplay cam girl',
        'roleplay webcam model',
        'fantasy cam girl',
        'roleplay cam model',
        'acting webcam model',
    ],
    
    'tattoo-piercing' => [
        'tattoo cam girl',
        'tattooed webcam model',
        'tattoo cam model',
        'pierced cam girl',
        'inked webcam model',
        'alt cam girl',
        'alternative webcam model',
    ],
    
    'uniforms' => [
        'uniform cam girl',
        'nurse webcam model',
        'schoolgirl cam girl',
        'maid webcam model',
        'secretary cam girl',
        'costume webcam model',
    ],
    
    /**
     * RELATIONSHIP/DYNAMIC
     */
    'couples' => [
        'couples live cam',
        'couple cam show',
        'couples webcam show',
        'duo cam show',
        'pair webcam show',
    ],
    
    /**
     * NICHE/SPECIAL
     */
    'fetish-lite' => [
        'fetish cam girl',
        'fetish webcam model',
        'kink cam girl',
        'fetish cam model',
        'specialty cam girl',
        'alternative webcam model',
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
        'cam sites',
        'webcam sites',
        'live cam',
        'cam models',
        'live webcam model',
        'cam girls',
        'webcam chat',
        'cam show',
        'private cam chat',
        'cam to cam chat',
        'free cam sites',
        'live cam chat',
    ],
];
