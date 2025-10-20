<?php
include __DIR__ . '/../includes/db.php';

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    // search accounts and transactions (simple LIKE search)
    try {
        $stmt = $conn->prepare("SELECT id, name as title FROM accounts WHERE name LIKE ? LIMIT 20");
        $stmt->execute(["%$q%"]);
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = ['type'=>'account','id'=>$r['id'],'title'=>$r['title'],'url'=>'/MY CASH/pages/account.php?id='.$r['id']];
    } catch (Exception $e) {}
    try {
        $stmt = $conn->prepare("SELECT id, notes as title FROM transactions WHERE notes LIKE ? LIMIT 20");
        $stmt->execute(["%$q%"]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = ['type'=>'transaction','id'=>$r['id'],'title'=>$r['title'],'url'=>'#'];
    } catch (Exception $e) {}
}

// if AJAX requested return JSON (used by header suggestions)
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card accent-5" style="margin:12px">
  <div class="card-title"><h3>Search</h3></div>
  <form method="GET">
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search...">
    <button>Search</button>
  </form>

  <div style="margin-top:12px">
    <?php if (empty($results)): ?>
      <p class="muted">No results</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach($results as $r): ?>
          <li><?=htmlspecialchars($r['type'])?>: <a href="<?=htmlspecialchars($r['url'] ?? '#')?>"><?=htmlspecialchars($r['title'])?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
