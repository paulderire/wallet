<?php
// includes/alerts.php
// Functions to check budgets and return alert messages

function getPeriodStart($period) {
    $now = new DateTime();
    if ($period === 'weekly') {
        $now->setTime(0,0,0);
        $day = $now->format('w'); // 0 (Sun) - 6 (Sat)
        $now->modify('-'.$day.' days'); // start of week (Sunday)
        return $now->format('Y-m-d 00:00:00');
    } elseif ($period === 'yearly') {
        return (new DateTime(date('Y-01-01 00:00:00')))->format('Y-m-d H:i:s');
    } else { // monthly
        return (new DateTime(date('Y-m-01 00:00:00')))->format('Y-m-d H:i:s');
    }
}

/**
 * Returns array of alert lines for budgets exceeded or near threshold.
 * Each item: ['budget'=>..., 'spent'=>..., 'percent'=>..., 'message'=>...]
 */
function checkBudgetAlerts(PDO $conn, $user_id) {
    $alerts = [];

    $stmt = $conn->prepare("SELECT * FROM budgets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($budgets as $b) {
        $periodStart = getPeriodStart($b['period']);
        // Sum expenses for this category in period across user's accounts
        $spendStmt = $conn->prepare("
            SELECT SUM(t.amount) as total
            FROM transactions t
            JOIN accounts a ON t.account_id = a.id
            WHERE a.user_id = ?
              AND t.type = 'expense'
              AND t.category = ?
              AND t.date >= ?
        ");
        $spendStmt->execute([$user_id, $b['category'], $periodStart]);
        $row = $spendStmt->fetch(PDO::FETCH_ASSOC);
        $spent = $row['total'] ? (float)$row['total'] : 0.0;

        $percent = $b['amount'] > 0 ? ($spent / $b['amount']) * 100 : 0;

        if ($percent >= $b['alert_threshold']) {
            $msg = ($percent >= 100)
                ? "Budget for '{$b['category']}' reached or exceeded ({$percent}% of {$b['amount']})."
                : "Budget for '{$b['category']}' has reached {$percent}% of {$b['amount']} (threshold: {$b['alert_threshold']}%).";
            $alerts[] = [
                'budget' => $b,
                'spent' => round($spent,2),
                'percent' => round($percent,2),
                'message' => $msg
            ];
        }
    }

    return $alerts;
}
