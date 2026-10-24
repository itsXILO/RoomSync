<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mock OTA Failure Rate
    |--------------------------------------------------------------------------
    |
    | Probability (from 0.0 to 1.0) of mock OTA endpoints returning HTTP 500
    | to simulate network/system outages and demo automatic queue retries.
    |
    */
    'mock_ota_fail_rate' => (float) env('MOCK_OTA_FAIL_RATE', 0),
];
