<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';

// Fetch employee details
$employee = null;
try {
  $stmt = $conn->prepare("
    SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) as full_name,
    TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) as age
    FROM employees e
    WHERE e.id = ?
  ");
  $stmt->execute([$employee_id]);
  $employee = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$employee) {
  header("Location: /MY CASH/employee/dashboard.php");
  exit;
}

// Fetch emergency contacts
$emergency_contacts = [];
try {
  $stmt = $conn->prepare("SELECT * FROM employee_emergency_contacts WHERE employee_id = ? ORDER BY is_primary DESC, contact_name");
  $stmt->execute([$employee_id]);
  $emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch documents
$documents = [];
try {
  $stmt = $conn->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC");
  $stmt->execute([$employee_id]);
  $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - Employee Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    .header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .header-title { font-size: 1.8rem; font-weight: 800; color: #1a202c; }
    .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(118,75,162,0.3); }
    .btn-secondary { background: rgba(0,0,0,0.05); color: #4a5568; }
    .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 24px; }
    .card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .avatar-section { text-align: center; }
    .avatar { width: 180px; height: 180px; border-radius: 50%; margin: 0 auto 20px; border: 4px solid #764ba2; overflow: hidden; background: linear-gradient(135deg, #764ba2, #667eea); }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: white; }
    .card h3 { font-size: 1.3rem; margin-bottom: 20px; color: #1a202c; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
    .info-row { display: grid; grid-template-columns: 140px 1fr; gap: 12px; padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
    .info-label { font-weight: 600; color: #718096; }
    .info-value { color: #1a202c; font-weight: 500; }
    .status-active { color: #10b981; font-weight: 700; text-transform: uppercase; }
    .contacts-grid { display: grid; gap: 16px; }
    .contact-card { background: rgba(118,75,162,0.05); border: 1px solid rgba(118,75,162,0.2); border-radius: 12px; padding: 16px; }
    .contact-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
    .contact-name { font-weight: 700; color: #1a202c; font-size: 1.1rem; }
    .primary-badge { background: linear-gradient(135deg, #764ba2, #667eea); color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .contact-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; font-size: 0.9rem; }
    .documents-grid { display: grid; gap: 16px; }
    .document-card { background: rgba(118,75,162,0.05); border: 1px solid rgba(118,75,162,0.2); border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center; }
    .document-name { font-weight: 700; color: #1a202c; margin-bottom: 4px; }
    .document-meta { font-size: 0.85rem; color: #718096; }
    .tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
    .tab { padding: 12px 24px; cursor: pointer; border: none; background: transparent; color: #718096; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; }
    .tab.active { color: #764ba2; border-bottom-color: #764ba2; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
    @media (max-width: 1024px) { .profile-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-content">
        <div>
          <h1 class="header-title">👤 My Profile</h1>
          <p style="color:#718096;margin-top:4px">View your personal information and documents</p>
        </div>
        <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
      </div>
    </div>

    <div class="profile-grid">
      <!-- Sidebar -->
      <div>
        <div class="card avatar-section">
          <div class="avatar">
            <?php if ($employee['avatar']): ?>
              <img src="/MY CASH/assets/uploads/avatars/<?= htmlspecialchars($employee['avatar']) ?>" alt="Avatar">
            <?php else: ?>
              <div class="avatar-placeholder">👤</div>
            <?php endif; ?>
          </div>
          <h3 style="margin:0 0 8px 0;border:none"><?= htmlspecialchars($employee['full_name']) ?></h3>
          <p style="color:#718096;margin:0 0 20px 0"><?= htmlspecialchars($employee['department'] ?? 'N/A') ?></p>
        </div>
        
        <div class="card" style="margin-top:20px">
          <h3>📋 Quick Info</h3>
          <div style="display:grid;gap:12px;font-size:0.9rem">
            <div>
              <div style="color:#718096;font-weight:600">Employee ID</div>
              <div style="color:#1a202c;font-weight:700"><?= htmlspecialchars($employee['employee_id']) ?></div>
            </div>
            <div>
              <div style="color:#718096;font-weight:600">Position</div>
              <div style="color:#1a202c"><?= htmlspecialchars($employee['role'] ?? 'N/A') ?></div>
            </div>
            <div>
              <div style="color:#718096;font-weight:600">Hire Date</div>
              <div style="color:#1a202c"><?= date('M d, Y', strtotime($employee['hire_date'])) ?></div>
            </div>
            <div>
              <div style="color:#718096;font-weight:600">Status</div>
              <div class="status-active"><?= htmlspecialchars($employee['status']) ?></div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Main Content -->
      <div class="card">
        <div class="tabs">
          <button class="tab active" onclick="switchTab('personal')">📝 Personal Info</button>
          <button class="tab" onclick="switchTab('emergency')">🚨 Emergency Contacts</button>
          <button class="tab" onclick="switchTab('documents')">📄 Documents</button>
        </div>
        
        <!-- Personal Info Tab -->
        <div id="personal-tab" class="tab-content active">
          <h3>Personal Information</h3>
          <div class="info-row">
            <span class="info-label">Full Name</span>
            <span class="info-value"><?= htmlspecialchars($employee['full_name']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value"><?= htmlspecialchars($employee['email'] ?? 'N/A') ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Phone</span>
            <span class="info-value"><?= htmlspecialchars($employee['phone'] ?? 'N/A') ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Date of Birth</span>
            <span class="info-value"><?= $employee['date_of_birth'] ? date('M d, Y', strtotime($employee['date_of_birth'])) : 'N/A' ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Age</span>
            <span class="info-value"><?= $employee['age'] ?? 'N/A' ?> years</span>
          </div>
          <div class="info-row">
            <span class="info-label">Address</span>
            <span class="info-value"><?= htmlspecialchars($employee['address'] ?? 'N/A') ?></span>
          </div>
          <div class="info-row" style="border:none">
            <span class="info-label">Salary</span>
            <span class="info-value" style="color:#10b981;font-weight:700"><?= number_format($employee['salary'] ?? 0, 0) ?> RWF</span>
          </div>
        </div>
        
        <!-- Emergency Contacts Tab -->
        <div id="emergency-tab" class="tab-content">
          <h3>Emergency Contacts</h3>
          <?php if (empty($emergency_contacts)): ?>
          <div class="empty-state">
            <div style="font-size:3rem">🚨</div>
            <h3>No Emergency Contacts</h3>
            <p>Contact your administrator to add emergency contacts</p>
          </div>
          <?php else: ?>
          <div class="contacts-grid">
            <?php foreach ($emergency_contacts as $contact): ?>
            <div class="contact-card">
              <div class="contact-header">
                <div>
                  <div class="contact-name"><?= htmlspecialchars($contact['contact_name']) ?></div>
                  <div style="color:#718096;font-size:0.9rem"><?= htmlspecialchars($contact['relationship']) ?></div>
                </div>
                <?php if ($contact['is_primary']): ?>
                <span class="primary-badge">PRIMARY</span>
                <?php endif; ?>
              </div>
              <div class="contact-details">
                <div>
                  <div style="color:#718096;font-weight:600">Phone</div>
                  <div style="color:#1a202c"><?= htmlspecialchars($contact['phone']) ?></div>
                </div>
                <?php if ($contact['alternate_phone']): ?>
                <div>
                  <div style="color:#718096;font-weight:600">Alternate</div>
                  <div style="color:#1a202c"><?= htmlspecialchars($contact['alternate_phone']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['email']): ?>
                <div>
                  <div style="color:#718096;font-weight:600">Email</div>
                  <div style="color:#1a202c"><?= htmlspecialchars($contact['email']) ?></div>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Documents Tab -->
        <div id="documents-tab" class="tab-content">
          <h3>My Documents</h3>
          <?php if (empty($documents)): ?>
          <div class="empty-state">
            <div style="font-size:3rem">📄</div>
            <h3>No Documents</h3>
            <p>No documents have been uploaded yet</p>
          </div>
          <?php else: ?>
          <div class="documents-grid">
            <?php foreach ($documents as $doc): ?>
            <div class="document-card">
              <div>
                <div class="document-name">📎 <?= htmlspecialchars($doc['document_name']) ?></div>
                <div class="document-meta">
                  <span><?= htmlspecialchars($doc['document_type']) ?></span> •
                  <span><?= number_format($doc['file_size'] / 1024, 1) ?> KB</span> •
                  <span><?= date('M d, Y', strtotime($doc['created_at'])) ?></span>
                  <?php if ($doc['expiry_date']): ?>
                  • <span style="color:<?= strtotime($doc['expiry_date']) < time() ? '#ef4444' : '#10b981' ?>">
                    Expires: <?= date('M d, Y', strtotime($doc['expiry_date'])) ?>
                  </span>
                  <?php endif; ?>
                </div>
              </div>
              <a href="/MY CASH/assets/uploads/documents/<?= htmlspecialchars($doc['file_path']) ?>" 
                 class="btn btn-primary" download style="padding:8px 16px;font-size:0.9rem">⬇️ Download</a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    function switchTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.getElementById(tabName + '-tab').classList.add('active');
      event.target.classList.add('active');
    }
  </script>
</body>
</html>
