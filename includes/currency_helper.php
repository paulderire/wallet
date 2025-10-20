<?php
/**
 * Currency Helper Functions
 * Handles RWF (Rwandan Franc) amounts with USD conversions
 * NOTE: All amounts in database are already in RWF
 */

// Exchange rate: 1,300 RWF = 1 USD
define('RWF_TO_USD_RATE', 1 / 1500);

/**
 * Format amount in RWF with USD equivalent below
 * 
 * @param float $amount The amount in RWF (no conversion needed)
 * @param bool $showUSD Whether to show USD conversion (default: true)
 * @return string HTML formatted amount
 */
function format_rwf_amount($amount, $showUSD = true) {
    $rwf = number_format($amount, 0, '.', ','); // RWF doesn't use decimals
    $usd = $amount * RWF_TO_USD_RATE;
    $usdFormatted = number_format($usd, 2, '.', ',');
    
    if ($showUSD) {
        return '<span class="dual-currency" data-amount-rwf="' . $amount . '" data-amount-usd="' . $usd . '">
                    <span class="primary-amount">RWF ' . $rwf . '</span>
                    <span class="secondary-amount">~$' . $usdFormatted . '</span>
                </span>';
    } else {
        return '<span class="dual-currency" data-amount-rwf="' . $amount . '">
                    <span class="primary-amount">RWF ' . $rwf . '</span>
                </span>';
    }
}

/**
 * Convert RWF to USD
 * 
 * @param float $rwf Amount in RWF
 * @return float Amount in USD
 */
function rwf_to_usd($rwf) {
    return $rwf * RWF_TO_USD_RATE;
}

/**
 * Get current exchange rate
 * 
 * @return array Exchange rate
 */
function get_exchange_rate() {
    return RWF_TO_USD_RATE;
}
