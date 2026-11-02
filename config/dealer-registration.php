<?php

// Optional, confirmed campaign data only. Null values are deliberately not replaced by demo data.
return [
    'group_url' => env('TOYOTA_COMMUNITY_GROUP_URL'),
    'group_name' => env('TOYOTA_COMMUNITY_GROUP_NAME'),
    'member_count' => env('TOYOTA_COMMUNITY_MEMBER_COUNT'),
    'campaign_start' => env('TOYOTA_COMMUNITY_CAMPAIGN_START'),
    'campaign_end' => env('TOYOTA_COMMUNITY_CAMPAIGN_END'),
    'terms_url' => env('TOYOTA_COMMUNITY_TERMS_URL'),
    'privacy_url' => env('TOYOTA_COMMUNITY_PRIVACY_URL'),
    'hotline' => env('TOYOTA_COMMUNITY_HOTLINE'),
];
