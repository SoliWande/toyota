<?php

// Only the official Group is a valid destination; never substitute a Facebook Page.
$officialGroupUrl = 'https://www.facebook.com/groups/376631239337942';
$configuredGroupUrl = trim((string) env('TOYOTA_COMMUNITY_GROUP_URL', $officialGroupUrl));

// Other campaign values remain optional and are not replaced by demo data.
return [
    // Operator-confirmed verified/public numbers, keyed by Dealer slug. Empty fails closed.
    // Demo numbers require their own explicit approval; never copy production approvals to demo.
    'approved_call_phones' => json_decode((string) env('TOYOTA_DEALER_APPROVED_CALL_PHONES', '{}'), true) ?: [],
    // Explicit public fallback permission: stable Dealer slug => its active Manager account ID.
    'manager_contacts' => json_decode((string) env('TOYOTA_DEALER_MANAGER_CONTACTS', '{}'), true) ?: [],
    'receipt_cleanup_enabled' => env('TOYOTA_RECEIPT_CLEANUP_ENABLED', false),
    'group_url' => rtrim($configuredGroupUrl, '/') === $officialGroupUrl ? $configuredGroupUrl : $officialGroupUrl,
    'group_name' => env('TOYOTA_COMMUNITY_GROUP_NAME'),
    'member_count' => env('TOYOTA_COMMUNITY_MEMBER_COUNT'),
    'campaign_start' => env('TOYOTA_COMMUNITY_CAMPAIGN_START'),
    'campaign_end' => env('TOYOTA_COMMUNITY_CAMPAIGN_END'),
    'terms_url' => env('TOYOTA_COMMUNITY_TERMS_URL'),
    'privacy_url' => env('TOYOTA_COMMUNITY_PRIVACY_URL'),
    'hotline' => env('TOYOTA_COMMUNITY_HOTLINE'),
];
