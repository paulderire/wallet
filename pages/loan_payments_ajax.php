<?php
// Simple JSON endpoint that returns loan payments for a loan id (user scoped)
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'not_authenticated']); exit; }
$user_id = $_SESSION['user_id'];
if (empty($_GET['loan_id'])) { echo json_encode(['ok'=>false,'error'=>'missing_loan_id']); exit; }
$loan_id = intval($_GET['loan_id']);
include __DIR__ . '/../includes/db.php';
try {
  $s = $conn->prepare("SELECT id, amount, payment_date, note, created_at FROM loan_payments WHERE loan_id=? AND user_id=? ORDER BY payment_date DESC, created_at DESC");
  $s->execute([$loan_id,$user_id]);
  $rows = $s->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'payments'=>$rows]);
} catch (Exception $e) {
  echo json_encode(['ok'=>false,'error'=>'db_error','message'=> $e->getMessage()]);
}
