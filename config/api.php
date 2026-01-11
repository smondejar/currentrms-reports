<?php
/**
 * CurrentRMS Report Builder - API Configuration
 *
 * Configure your CurrentRMS API credentials here.
 * Get these from System Setup > Integrations > API in CurrentRMS.
 */

return [
    'subdomain' => getenv('CURRENTRMS_SUBDOMAIN') ?: '',
    'api_token' => getenv('CURRENTRMS_API_TOKEN') ?: '',
    'base_url' => 'https://api.current-rms.com/api/v1',
    'timeout' => 30,
    'verify_ssl' => true,
];
