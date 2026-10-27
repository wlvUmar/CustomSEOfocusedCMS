<?php
// path: ./config/stock_queries.php
// Page slug → image-search words for the admin stock-photo search (Pexels works best in English).
// Edit freely — no code changes needed. A value is a string, or a list: the FIRST is searched by default and the
// rest show up as one-click alternatives ("chips") under the search box.
//
// Lookup order (first hit wins):
//   1. 'exact'          — the page slug exactly
//   2. 'slug_patterns'  — regex tested against the slug only (product-code prefixes: lg-f2j… = LG washer)
//   3. 'text_patterns'  — regex tested against slug + Russian title
//   4. the same three steps for the PARENT page (model pages usually sit under a category)
//   5. 'default'
// The patterns below are a best guess from model codes — check a few and correct what is wrong.
return [
    'exact' => [
        'main'        => ['home appliances', 'used appliances', 'household appliances'],
        'xolodilnik'  => ['refrigerator kitchen', 'fridge freezer', 'side by side refrigerator'],
        'morozilnik'  => ['freezer', 'chest freezer', 'refrigerator kitchen'],
        'stiralka'    => ['washing machine laundry', 'front load washer', 'laundry room'],
        'televizor'   => ['television living room', 'smart tv', 'flat screen tv'],
        'konditsioner' => ['air conditioner', 'split air conditioner wall', 'air conditioning room'],
        'gas-plita'   => ['gas stove kitchen', 'gas cooker', 'kitchen hob'],
        'kovri-polas' => ['carpet rug living room', 'persian rug', 'carpet floor'],
        'divan'       => ['sofa living room', 'corner sofa', 'couch interior'],
        'kravat'      => ['bed bedroom', 'double bed', 'bedroom interior'],
        'shkaf'       => ['wardrobe bedroom', 'closet wardrobe', 'sliding wardrobe'],
        'kuxnniy'     => ['kitchen furniture', 'modern kitchen', 'kitchen cabinets'],
        'stol-stul'   => ['dining table chairs', 'dining room', 'kitchen table'],
        'mebel'       => ['furniture home interior', 'living room furniture', 'bedroom furniture'],
    ],

    'slug_patterns' => [
        // refrigerators / freezers
        ['/^samsung-(rb|rt|rs|rz)\d/i', ['refrigerator kitchen', 'samsung refrigerator']],
        ['/^lg-g[abcnls]-/i',           ['refrigerator kitchen', 'lg refrigerator']],
        ['/^bosch-kg/i',                ['refrigerator kitchen', 'bosch refrigerator']],
        ['/^(indesit-df|atlant-|beko-fsk|artel-hd)/i', 'refrigerator kitchen'],
        ['/^liebherr-/i',               ['refrigerator kitchen', 'freezer']],
        ['/^artel-afd/i',               ['chest freezer', 'freezer']],
        // washing machines
        ['/^samsung-ww/i',              ['washing machine laundry', 'samsung washing machine']],
        ['/^lg-f\d/i',                  ['washing machine laundry', 'lg washing machine']],
        ['/^(bosch-wa|indesit-bw)/i',   'washing machine laundry'],
        // televisions
        ['/^samsung-(ue|qe)\d/i',       ['television living room', 'samsung tv']],
        ['/^lg-(\d{2}up|oled|\d{2}nano)/i', ['television living room', 'lg oled tv']],
        ['/^(sony-(kd|xr)|shivaki-stv|artel-ua)/i', ['television living room', 'smart tv']],
        // air conditioners
        ['/^samsung-ar\d/i',            ['air conditioner', 'samsung air conditioner']],
        ['/^lg-(s\d|dual-cool|artcool)/i', ['air conditioner', 'lg air conditioner']],
        ['/^(gree-|midea-|artel-art-)/i', 'air conditioner'],
        // gas stoves / hobs / cookers
        ['/^(gefest-|gorenje-|zanussi-|hotpoint-|hansa-|bosch-hgv|artel-(apetito|milagro))/i', ['gas stove kitchen', 'gas cooker']],
        // IKEA beds (before the carpet size rule)
        ['/^(malm|hemnes|brimnes)-/i',  ['bed bedroom', 'double bed']],
        // carpets / rugs
        ['/^(merinos|brillant|isfahan|tabriz|platinum|ragolle|karmen|kristal|arte)-/i', ['carpet rug living room', 'persian rug']],
        // furniture sets
        ['/^(bellona|istikbal|alfemo|dreamline|comfort-lux|soho|verona)-/i', ['bedroom furniture', 'sofa living room']],
    ],

    'text_patterns' => [
        ['/холодил|морозил|xolodil|morozil|fridge|refriger/iu', ['refrigerator kitchen', 'fridge freezer']],
        ['/стирал|stiral|washing|washer/iu',                    ['washing machine laundry', 'front load washer']],
        ['/телевиз|televiz|\btv\b/iu',                          ['television living room', 'smart tv']],
        ['/кондиц|konditsion|air-?cond|split/iu',               ['air conditioner', 'split air conditioner wall']],
        ['/газов|плит|plita|stove|cooker/iu',                   ['gas stove kitchen', 'gas cooker']],
        ['/ковер|ковр|kovr|kovri|carpet|rug/iu',                ['carpet rug living room', 'persian rug']],
        ['/диван|divan|sofa/iu',                                ['sofa living room', 'corner sofa']],
        ['/кроват|матрас|kravat|bed\b/iu',                      ['bed bedroom', 'double bed']],
        ['/шкаф|shkaf|wardrobe/iu',                             ['wardrobe bedroom', 'closet wardrobe']],
        ['/кухн|kuxn|kitchen/iu',                               ['kitchen furniture', 'modern kitchen']],
        ['/стол|стул|stol|stul/iu',                             ['dining table chairs', 'dining room']],
        ['/мебел|mebel|furniture/iu',                           ['furniture home interior', 'living room furniture']],
    ],

    'default' => ['home appliances', 'household appliances'],
];
