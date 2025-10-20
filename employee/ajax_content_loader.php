<?php
/**
 * AJAX Content Loader for Employee Portal
 * Returns page content without header/footer for single-page app navigation
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Check if employee is logged in
if (empty($_SESSION['employee_id'])) {
  http_response_code(401);
  die('<div style="text-align:center;padding:60px 20px;"><h2 style="color:var(--card-text);">Session Expired</h2><p style="color:var(--muted);">Please <a href="/MY CASH/employee_login.php">login again</a>.</p></div>');
}

// Get requested page
$page = $_GET['page'] ?? '';

// Whitelist of allowed pages
$allowed_pages = [
  'my_profile',
  'my_attendance', 
  'my_payments',
  'my_corrections',
  'record_transaction',
  'manage_inventory',
  'report_stock',
  'chat'
];

if (!in_array($page, $allowed_pages)) {
  http_response_code(404);
  die('<div style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">🔍</div>
    <h2 style="color: var(--card-text); margin-bottom: 10px;">Page Not Found</h2>
    <p style="color: var(--muted);">The requested page does not exist.</p>
  </div>');
}

// Include dependencies
include __DIR__ . '/../includes/db.php';
if (file_exists(__DIR__ . '/../includes/currency.php')) {
  include __DIR__ . '/../includes/currency.php';
}

// Set employee variables
$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_role = $_SESSION['employee_role'] ?? '';
$user_id = $_SESSION['employee_user_id'] ?? 0;

// Define flag for content-only mode
define('CONTENT_ONLY', true);

// Load page file
$page_file = __DIR__ . '/' . $page . '.php';

if (!file_exists($page_file)) {
  http_response_code(404);
  die('<div style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">⚠️</div>
    <h2 style="color: var(--card-text);">Page File Not Found</h2>
    <p style="color: var(--muted);">Unable to locate: ' . htmlspecialchars($page) . '.php</p>
  </div>');
}

// Capture output
ob_start();

try {
  // Read file content
  $file_content = file_get_contents($page_file);
  
  // Check if file uses includes for header/footer
  $has_header = strpos($file_content, "include") !== false && strpos($file_content, "header.php") !== false;
  $has_footer = strpos($file_content, "include") !== false && strpos($file_content, "footer.php") !== false;
  
  if ($has_header || $has_footer) {
    // Page has full HTML structure - extract body content
    // Create temporary functions to skip header/footer
    $GLOBALS['skip_header'] = true;
    $GLOBALS['skip_footer'] = true;
    
    // Execute the page
    include $page_file;
    
    $output = ob_get_clean();
    
    // Try to extract main content (between header and footer)
    // Look for content between common page markers
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $output, $matches)) {
      $output = $matches[1];
    }
    
    // Remove header and footer divs
    $output = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $output);
    $output = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $output);
    $output = preg_replace('/<nav[^>]*class="[^"]*sidebar[^"]*"[^>]*>.*?<\/nav>/is', '', $output);
    
    // Wrap output in a themed container with inline styles for immediate theme support
    echo '<div class="ajax-loaded-content" style="background: var(--card-bg); color: var(--card-text); min-height: 400px; padding: 20px; border-radius: 16px;">';
    echo '<style>
      .ajax-loaded-content * { 
        color: inherit; 
      }
      .ajax-loaded-content input,
      .ajax-loaded-content textarea,
      .ajax-loaded-content select {
        background: var(--card-bg);
        color: var(--card-text);
        border: 1px solid var(--border-medium);
      }
      .ajax-loaded-content table {
        background: var(--card-bg);
        color: var(--card-text);
      }
      .ajax-loaded-content th,
      .ajax-loaded-content td {
        color: var(--card-text);
        border-color: var(--border-weak);
      }
      .ajax-loaded-content .card,
      .ajax-loaded-content .employee-card {
        background: var(--card-bg);
        color: var(--card-text);
        border-color: var(--border-weak);
      }
    </style>';
    echo $output;
    echo '</div>';
    
  } else {
    // Simple page - just include it
    include $page_file;
    $output = ob_get_clean();
    
    // Wrap in themed container with inline styles
    echo '<div class="ajax-loaded-content" style="background: var(--card-bg); color: var(--card-text); min-height: 400px; padding: 20px; border-radius: 16px;">';
    echo '<style>
      .ajax-loaded-content * { 
        color: inherit; 
      }
      .ajax-loaded-content input,
      .ajax-loaded-content textarea,
      .ajax-loaded-content select {
        background: var(--card-bg);
        color: var(--card-text);
        border: 1px solid var(--border-medium);
      }
      .ajax-loaded-content table {
        background: var(--card-bg);
        color: var(--card-text);
      }
      .ajax-loaded-content th,
      .ajax-loaded-content td {
        color: var(--card-text);
        border-color: var(--border-weak);
      }
      .ajax-loaded-content .card,
      .ajax-loaded-content .employee-card {
        background: var(--card-bg);
        color: var(--card-text);
        border-color: var(--border-weak);
      }
    </style>';
    echo $output;
    echo '</div>';
  }
  
} catch (Exception $e) {
  ob_end_clean();
  error_log("AJAX Content Loader Error: " . $e->getMessage());
  http_response_code(500);
  echo '<div style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">⚠️</div>
    <h2 style="color: var(--card-text);">Error Loading Content</h2>
    <p style="color: var(--muted);">An error occurred while loading the page.</p>
    <p style="color: var(--muted); font-size: 12px; margin-top: 10px;">' . htmlspecialchars($e->getMessage()) . '</p>
  </div>';
}

