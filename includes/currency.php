<?php
/**
 * Currency Configuration
 * Exchange rate for RWF to USD conversion
 */

// Exchange rate: 1 USD = 1500 RWF
if (!defined('USD_TO_RWF')) {
    define('USD_TO_RWF', 1500);
}

/**
 * Convert RWF to USD
 * @param float $rwf Amount in RWF
 * @return float Amount in USD
 */
if (!function_exists('rwf_to_usd')) {
    function rwf_to_usd($rwf) {
        return $rwf / USD_TO_RWF;
    }
}

/**
 * Convert USD to RWF
 * @param float $usd Amount in USD
 * @return float Amount in RWF
 */
if (!function_exists('usd_to_rwf')) {
    function usd_to_rwf($usd) {
        return $usd * USD_TO_RWF;
    }
}

/**
 * Format amount with both RWF and USD
 * @param float $rwf Amount in RWF
 * @param bool $show_rwf Show RWF amount (default: true)
 * @param bool $show_usd Show USD amount (default: true)
 * @return string Formatted string
 */
if (!function_exists('format_currency')) {
    function format_currency($rwf, $show_rwf = true, $show_usd = true) {
        $output = '';
        
        if ($show_rwf) {
            $output .= 'RWF ' . number_format($rwf, 0);
        }
        
        if ($show_rwf && $show_usd) {
            $output .= ' ($' . number_format(rwf_to_usd($rwf), 2) . ')';
        } elseif ($show_usd) {
            $output .= '$' . number_format(rwf_to_usd($rwf), 2);
        }
        
        return $output;
    }
}
?>
