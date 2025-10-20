<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /MY CASH/pages/login.php'); exit; }
include __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'];

// Check if user is admin
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$is_admin = !empty($user['is_admin']);

if (!$is_admin) {
    header('Location: /MY CASH/pages/dashboard.php');
    exit;
}

// Auto-create tables if they don't exist
try {
    $schema = file_get_contents(__DIR__ . '/../db/employee_profile_schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
} catch (Exception $e) {}

$success_msg = $error_msg = '';

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF allowed.");
            }
            
            // Create uploads directory if not exists
            $upload_dir = __DIR__ . '/../assets/uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = 'avatar_' . $employee_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                // Update database
                $stmt = $conn->prepare("UPDATE employees SET avatar = ? WHERE id = ?");
                $stmt->execute([$new_filename, $employee_id]);
                $success_msg = "Avatar uploaded successfully!";
            } else {
                throw new Exception("Failed to upload file.");
            }
        }
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $date_of_birth = $_POST['date_of_birth'] ?: null;
        
        $stmt = $conn->prepare("
            UPDATE employees 
            SET email = ?, phone = ?, address = ?, date_of_birth = ?
            WHERE id = ?
        ");
        $stmt->execute([$email, $phone, $address, $date_of_birth, $employee_id]);
        
        $success_msg = "Profile updated successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle emergency contact add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_emergency_contact'])) {
    try {
        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $employee_id = intval($_POST['employee_id']);
        $contact_name = trim($_POST['contact_name']);
        $relationship = trim($_POST['relationship']);
        $phone = trim($_POST['contact_phone']);
        $alternate_phone = trim($_POST['alternate_phone'] ?? '');
        $email = trim($_POST['contact_email'] ?? '');
        $address = trim($_POST['contact_address'] ?? '');
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        if ($contact_id > 0) {
            // Update existing
            $stmt = $conn->prepare("
                UPDATE employee_emergency_contacts 
                SET contact_name = ?, relationship = ?, phone = ?, 
                    alternate_phone = ?, email = ?, address = ?, is_primary = ?
                WHERE id = ? AND employee_id = ?
            ");
            $stmt->execute([$contact_name, $relationship, $phone, $alternate_phone, $email, $address, $is_primary, $contact_id, $employee_id]);
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO employee_emergency_contacts 
                (employee_id, contact_name, relationship, phone, alternate_phone, email, address, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employee_id, $contact_name, $relationship, $phone, $alternate_phone, $email, $address, $is_primary]);
        }
        
        $success_msg = "Emergency contact saved successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle emergency contact delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contact'])) {
    try {
        $contact_id = intval($_POST['contact_id']);
        $stmt = $conn->prepare("DELETE FROM employee_emergency_contacts WHERE id = ?");
        $stmt->execute([$contact_id]);
        $success_msg = "Emergency contact deleted successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $document_type = trim($_POST['document_type']);
        $document_name = trim($_POST['document_name']);
        $expiry_date = $_POST['expiry_date'] ?: null;
        $notes = trim($_POST['document_notes'] ?? '');
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $filename = $_FILES['document']['name'];
            $file_size = $_FILES['document']['size'];
            $file_type = $_FILES['document']['type'];
            
            // Create uploads directory if not exists
            $upload_dir = __DIR__ . '/../assets/uploads/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $new_filename = 'doc_' . $employee_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO employee_documents 
                    (employee_id, document_type, document_name, file_path, file_size, file_type, expiry_date, notes, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$employee_id, $document_type, $document_name, $new_filename, $file_size, $file_type, $expiry_date, $notes, $user_id]);
                $success_msg = "Document uploaded successfully!";
            } else {
                throw new Exception("Failed to upload document.");
            }
        }
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle document delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    try {
        $document_id = intval($_POST['document_id']);
        
        // Get file path before deleting
        $stmt = $conn->prepare("SELECT file_path FROM employee_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc) {
            // Delete file
            $file_path = __DIR__ . '/../assets/uploads/documents/' . $doc['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM employee_documents WHERE id = ?");
            $stmt->execute([$document_id]);
            $success_msg = "Document deleted successfully!";
        }
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Fetch employee ID from query parameter
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch employee details
$employee = null;
if ($employee_id) {
    $stmt = $conn->prepare("
        SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) as full_name,
        TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) as age
        FROM employees e
        WHERE e.id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all employees for dropdown
$employees = [];
try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, department FROM employees ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch emergency contacts
$emergency_contacts = [];
if ($employee_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM employee_emergency_contacts WHERE employee_id = ? ORDER BY is_primary DESC, contact_name");
        $stmt->execute([$employee_id]);
        $emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Fetch documents
$documents = [];
if ($employee_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC");
        $stmt->execute([$employee_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.page-title{font-size:2rem;font-weight:800;background:linear-gradient(135deg,#764ba2,#667eea);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin:0}
.employee-selector{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px;margin-bottom:24px}
.employee-selector select{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);font-size:1rem}
.profile-grid{display:grid;grid-template-columns:300px 1fr;gap:24px;margin-bottom:24px}
.profile-sidebar{display:flex;flex-direction:column;gap:20px}
.avatar-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px;text-align:center}
.avatar-preview{width:180px;height:180px;border-radius:50%;margin:0 auto 20px;overflow:hidden;border:4px solid var(--border-weak);background:linear-gradient(135deg,#764ba2,#667eea)}
.avatar-preview img{width:100%;height:100%;object-fit:cover}
.avatar-preview .placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:4rem;color:#fff}
.info-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px}
.info-card h3{margin:0 0 20px 0;font-size:1.3rem;color:var(--card-text);display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:2px solid var(--border-weak)}
.tabs{display:flex;gap:8px;margin-bottom:20px;border-bottom:2px solid var(--border-weak)}
.tab{padding:12px 24px;cursor:pointer;border:none;background:transparent;color:var(--muted);font-weight:600;border-bottom:3px solid transparent;transition:all 0.2s}
.tab.active{color:#764ba2;border-bottom-color:#764ba2}
.tab-content{display:none}
.tab-content.active{display:block}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;color:var(--card-text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text)}
.contacts-list{display:grid;gap:16px}
.contact-card{background:rgba(118,75,162,0.05);border:1px solid var(--border-weak);border-radius:12px;padding:16px}
.contact-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:12px}
.contact-name{font-weight:700;font-size:1.1rem;color:var(--card-text)}
.primary-badge{background:linear-gradient(135deg,#764ba2,#667eea);color:#fff;padding:4px 12px;border-radius:12px;font-size:0.75rem;font-weight:600;text-transform:uppercase}
.contact-details{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;font-size:0.9rem}
.contact-label{color:var(--muted);font-weight:600}
.contact-value{color:var(--card-text)}
.documents-grid{display:grid;gap:16px}
.document-card{background:rgba(118,75,162,0.05);border:1px solid var(--border-weak);border-radius:12px;padding:16px;display:flex;justify-content:space-between;align-items:center}
.document-info{flex:1}
.document-name{font-weight:700;color:var(--card-text);margin-bottom:4px}
.document-meta{font-size:0.85rem;color:var(--muted)}
.document-actions{display:flex;gap:8px}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:var(--card-bg);border-radius:16px;padding:32px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
.modal-header{font-size:1.5rem;font-weight:700;margin-bottom:24px;color:var(--card-text)}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state h3{margin:20px 0 10px 0}
@media (max-width:1024px){.profile-grid{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}}
</style>

<div class="page-header">
    <h1 class="page-title">👤 Employee Profile Management</h1>
    <button class="button primary" onclick="location.reload()">🔄 Refresh</button>
</div>

<?php if ($success_msg): ?>
<div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<div class="employee-selector">
    <label style="display:block;margin-bottom:12px;font-weight:600;color:var(--card-text)">Select Employee</label>
    <select onchange="window.location.href='/MY CASH/pages/employee_profile.php?id=' + this.value">
        <option value="">Choose an employee...</option>
        <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['department'] ?? 'N/A') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<?php if ($employee): ?>

<div class="profile-grid">
    <!-- Sidebar -->
    <div class="profile-sidebar">
        <div class="avatar-card">
            <div class="avatar-preview">
                <?php if ($employee['avatar']): ?>
                    <img src="/MY CASH/assets/uploads/avatars/<?= htmlspecialchars($employee['avatar']) ?>" alt="Avatar">
                <?php else: ?>
                    <div class="placeholder">👤</div>
                <?php endif; ?>
            </div>
            <h3 style="margin:0 0 8px 0"><?= htmlspecialchars($employee['full_name']) ?></h3>
            <p style="color:var(--muted);margin:0 0 20px 0"><?= htmlspecialchars($employee['department'] ?? 'N/A') ?></p>
            <button class="button primary" onclick="openModal('avatarModal')">📷 Change Photo</button>
        </div>
        
        <div class="info-card">
            <h3>📋 Quick Info</h3>
            <div style="display:grid;gap:12px;font-size:0.9rem">
                <div>
                    <div style="color:var(--muted);font-weight:600">Employee ID</div>
                    <div style="color:var(--card-text);font-weight:700"><?= htmlspecialchars($employee['employee_id']) ?></div>
                </div>
                <div>
                    <div style="color:var(--muted);font-weight:600">Position</div>
                    <div style="color:var(--card-text)"><?= htmlspecialchars($employee['role'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <div style="color:var(--muted);font-weight:600">Hire Date</div>
                    <div style="color:var(--card-text)"><?= date('M d, Y', strtotime($employee['hire_date'])) ?></div>
                </div>
                <div>
                    <div style="color:var(--muted);font-weight:600">Status</div>
                    <div style="color:<?= $employee['status'] === 'active' ? '#10b981' : '#ef4444' ?>;font-weight:700;text-transform:uppercase">
                        <?= htmlspecialchars($employee['status']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="info-card">
        <div class="tabs">
            <button class="tab active" onclick="switchTab('personal')">📝 Personal Info</button>
            <button class="tab" onclick="switchTab('emergency')">🚨 Emergency Contacts</button>
            <button class="tab" onclick="switchTab('documents')">📄 Documents</button>
        </div>
        
        <!-- Personal Info Tab -->
        <div id="personal-tab" class="tab-content active">
            <form method="POST">
                <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" value="<?= htmlspecialchars($employee['first_name']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" value="<?= htmlspecialchars($employee['last_name']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($employee['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?= $employee['date_of_birth'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="text" value="<?= $employee['age'] ?? 'N/A' ?> years" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3"><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="update_profile" class="button primary">💾 Update Profile</button>
            </form>
        </div>
        
        <!-- Emergency Contacts Tab -->
        <div id="emergency-tab" class="tab-content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h4 style="margin:0">Emergency Contacts</h4>
                <button class="button primary" onclick="openAddContactModal()">➕ Add Contact</button>
            </div>
            
            <?php if (empty($emergency_contacts)): ?>
            <div class="empty-state">
                <div style="font-size:3rem">🚨</div>
                <h3>No Emergency Contacts</h3>
                <p>Add emergency contact information for this employee</p>
            </div>
            <?php else: ?>
            <div class="contacts-list">
                <?php foreach ($emergency_contacts as $contact): ?>
                <div class="contact-card">
                    <div class="contact-header">
                        <div>
                            <div class="contact-name"><?= htmlspecialchars($contact['contact_name']) ?></div>
                            <div style="color:var(--muted);font-size:0.9rem"><?= htmlspecialchars($contact['relationship']) ?></div>
                        </div>
                        <?php if ($contact['is_primary']): ?>
                        <span class="primary-badge">PRIMARY</span>
                        <?php endif; ?>
                    </div>
                    <div class="contact-details">
                        <div>
                            <div class="contact-label">Phone</div>
                            <div class="contact-value"><?= htmlspecialchars($contact['phone']) ?></div>
                        </div>
                        <?php if ($contact['alternate_phone']): ?>
                        <div>
                            <div class="contact-label">Alternate Phone</div>
                            <div class="contact-value"><?= htmlspecialchars($contact['alternate_phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($contact['email']): ?>
                        <div>
                            <div class="contact-label">Email</div>
                            <div class="contact-value"><?= htmlspecialchars($contact['email']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($contact['address']): ?>
                        <div style="grid-column:1/-1">
                            <div class="contact-label">Address</div>
                            <div class="contact-value"><?= htmlspecialchars($contact['address']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:12px">
                        <button class="button secondary" onclick="editContact(<?= htmlspecialchars(json_encode($contact)) ?>)">✏️ Edit</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this contact?')">
                            <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                            <button type="submit" name="delete_contact" class="button danger">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Documents Tab -->
        <div id="documents-tab" class="tab-content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h4 style="margin:0">Employee Documents</h4>
                <button class="button primary" onclick="openModal('documentModal')">📎 Upload Document</button>
            </div>
            
            <?php if (empty($documents)): ?>
            <div class="empty-state">
                <div style="font-size:3rem">📄</div>
                <h3>No Documents</h3>
                <p>Upload documents such as ID cards, contracts, certificates, etc.</p>
            </div>
            <?php else: ?>
            <div class="documents-grid">
                <?php foreach ($documents as $doc): ?>
                <div class="document-card">
                    <div class="document-info">
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
                        <?php if ($doc['notes']): ?>
                        <div style="font-size:0.85rem;color:var(--muted);margin-top:4px"><?= htmlspecialchars($doc['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="document-actions">
                        <a href="/MY CASH/assets/uploads/documents/<?= htmlspecialchars($doc['file_path']) ?>" 
                           class="button secondary" download>⬇️ Download</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this document?')">
                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                            <button type="submit" name="delete_document" class="button danger">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Avatar Upload Modal -->
<div id="avatarModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">📷 Upload Avatar</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
            <div class="form-group">
                <label>Select Photo (JPG, PNG, GIF)</label>
                <input type="file" name="avatar" accept="image/*" required>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="upload_avatar" class="button primary">📤 Upload</button>
                <button type="button" class="button secondary" onclick="closeModal('avatarModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Emergency Contact Modal -->
<div id="contactModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" id="contactModalTitle">➕ Add Emergency Contact</div>
        <form method="POST">
            <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
            <input type="hidden" name="contact_id" id="contact_id" value="0">
            <div class="form-grid">
                <div class="form-group">
                    <label>Contact Name *</label>
                    <input type="text" name="contact_name" id="contact_name" required>
                </div>
                <div class="form-group">
                    <label>Relationship *</label>
                    <select name="relationship" id="relationship" required>
                        <option value="">Select...</option>
                        <option value="Spouse">Spouse</option>
                        <option value="Parent">Parent</option>
                        <option value="Sibling">Sibling</option>
                        <option value="Child">Child</option>
                        <option value="Friend">Friend</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" name="contact_phone" id="contact_phone" required>
                </div>
                <div class="form-group">
                    <label>Alternate Phone</label>
                    <input type="tel" name="alternate_phone" id="alternate_phone">
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="contact_email" id="contact_email">
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="contact_address" id="contact_address" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="is_primary" id="is_primary">
                    <span>Primary Emergency Contact</span>
                </label>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="save_emergency_contact" class="button primary">💾 Save Contact</button>
                <button type="button" class="button secondary" onclick="closeModal('contactModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Document Upload Modal -->
<div id="documentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">📎 Upload Document</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
            <div class="form-group">
                <label>Document Type *</label>
                <select name="document_type" required>
                    <option value="">Select...</option>
                    <option value="ID Card">ID Card</option>
                    <option value="Passport">Passport</option>
                    <option value="Contract">Employment Contract</option>
                    <option value="Certificate">Certificate</option>
                    <option value="Diploma">Diploma/Degree</option>
                    <option value="Resume">Resume/CV</option>
                    <option value="Reference">Reference Letter</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Document Name *</label>
                <input type="text" name="document_name" placeholder="e.g., National ID Card" required>
            </div>
            <div class="form-group">
                <label>Select File *</label>
                <input type="file" name="document" required>
            </div>
            <div class="form-group">
                <label>Expiry Date (if applicable)</label>
                <input type="date" name="expiry_date">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="document_notes" rows="2" placeholder="Additional information..."></textarea>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="upload_document" class="button primary">📤 Upload</button>
                <button type="button" class="button secondary" onclick="closeModal('documentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="empty-state" style="background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:60px 20px">
    <div style="font-size:4rem">👤</div>
    <h2>Select an Employee</h2>
    <p>Choose an employee from the dropdown above to manage their profile</p>
</div>
<?php endif; ?>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function openAddContactModal() {
    document.getElementById('contactModalTitle').textContent = '➕ Add Emergency Contact';
    document.getElementById('contact_id').value = '0';
    document.getElementById('contact_name').value = '';
    document.getElementById('relationship').value = '';
    document.getElementById('contact_phone').value = '';
    document.getElementById('alternate_phone').value = '';
    document.getElementById('contact_email').value = '';
    document.getElementById('contact_address').value = '';
    document.getElementById('is_primary').checked = false;
    openModal('contactModal');
}

function editContact(contact) {
    document.getElementById('contactModalTitle').textContent = '✏️ Edit Emergency Contact';
    document.getElementById('contact_id').value = contact.id;
    document.getElementById('contact_name').value = contact.contact_name;
    document.getElementById('relationship').value = contact.relationship;
    document.getElementById('contact_phone').value = contact.phone;
    document.getElementById('alternate_phone').value = contact.alternate_phone || '';
    document.getElementById('contact_email').value = contact.email || '';
    document.getElementById('contact_address').value = contact.address || '';
    document.getElementById('is_primary').checked = contact.is_primary == 1;
    openModal('contactModal');
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
