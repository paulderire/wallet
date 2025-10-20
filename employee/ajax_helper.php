<?php
/**
 * Simple AJAX Page Wrapper
 * Detects AJAX requests and returns content without header/footer
 */

// Check if this is an AJAX request
function is_ajax_request() {
  return !empty($_GET['ajax']) || 
         !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Start AJAX mode if requested
function ajax_start() {
  if (is_ajax_request()) {
    ob_start();
    define('AJAX_MODE', true);
  }
}

// End AJAX mode and output content
function ajax_end() {
  if (defined('AJAX_MODE') && AJAX_MODE) {
    $content = ob_get_clean();
    
    // Remove any header/footer includes from output
    $content = preg_replace('/.*?\/\/ AJAX_CONTENT_START(.*?)\/\/ AJAX_CONTENT_END.*/s', '$1', $content);
    
    echo $content;
    exit;
  }
}

// Skip header in AJAX mode
function ajax_header_skip() {
  return defined('AJAX_MODE') && AJAX_MODE;
}

// Skip footer in AJAX mode  
function ajax_footer_skip() {
  return defined('AJAX_MODE') && AJAX_MODE;
}
