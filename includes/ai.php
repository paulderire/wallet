<?php
// includes/ai.php
// Simple heuristic "AI" analysis: top categories, wants vs needs, basic recommendations

/**
 * Returns an analysis array:
 * - top_categories: [ ['category'=>..,'total'=>..], ... ]
 * - needs_vs_wants: ['needs'=>amount,'wants'=>amount,'breakdown'=>[cat=>type], ...]
 * - recommendations: [string, ...]
 */
function analyzeSpending(PDO $conn, $user_id, $months = 3) {
    $analysis = [
        'top_categories' => [],
        'needs_vs_wants' => ['needs'=>0,'wants'=>0,'breakdown'=>[]],
        'recommendations' => []
    ];

    // 1) Top categories (last $months months)
    $start = (new DateTime())->modify("-{$months} months")->format('Y-m-d 00:00:00');
    $stmt = $conn->prepare("
        SELECT t.category, SUM(t.amount) as total
        FROM transactions t
        JOIN accounts a ON t.account_id = a.id
        WHERE a.user_id = ? AND t.type = 'expense' AND t.date >= ?
        GROUP BY t.category
        ORDER BY total DESC
        LIMIT 6
    ");
    $stmt->execute([$user_id, $start]);
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $analysis['top_categories'] = $cats;

    // 2) Wants vs Needs classification (simple heuristic)
    // Define common 'needs' categories - you can extend this list
    $needs_list = [
        'rent','mortgage','utilities','electricity','water','gas',
        'groceries','food','healthcare','insurance','transportation',
        'fuel','public transport','education','loan','debt'
    ];

    // Get totals per category (all time or last months)
    $totStmt = $conn->prepare("
        SELECT t.category, SUM(t.amount) as total
        FROM transactions t
        JOIN accounts a ON t.account_id = a.id
        WHERE a.user_id = ? AND t.type = 'expense' AND t.date >= ?
        GROUP BY t.category
    ");
    $totStmt->execute([$user_id, $start]);
    $allcats = $totStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($allcats as $c) {
        $catLower = strtolower($c['category']);
        $isNeed = false;
        foreach($needs_list as $needTerm) {
            if (strpos($catLower, $needTerm) !== false) { $isNeed = true; break; }
        }
        if ($isNeed) {
            $analysis['needs_vs_wants']['needs'] += (float)$c['total'];
            $analysis['needs_vs_wants']['breakdown'][$c['category']] = 'need';
        } else {
            $analysis['needs_vs_wants']['wants'] += (float)$c['total'];
            $analysis['needs_vs_wants']['breakdown'][$c['category']] = 'want';
        }
    }

    // 3) Generate recommendations
    // If top wants consume a large share, recommend reductions
    $needs = $analysis['needs_vs_wants']['needs'];
    $wants = $analysis['needs_vs_wants']['wants'];
    $total = $needs + $wants;

    if ($total > 0) {
        $want_percent = ($wants / $total) * 100;
        if ($want_percent > 35) {
            $analysis['recommendations'][] = "Your discretionary spending (wants) is {$want_percent}% of total expenses. Consider trimming non-essential categories by 10%-20%.";
        } else {
            $analysis['recommendations'][] = "Good job: discretionary spending is {$want_percent}% of total expenses (target <35%). Keep it up.";
        }
    } else {
        $analysis['recommendations'][] = "No expense data found for the selected period to analyze.";
    }

    // Suggest turning savings into goals if recurring surplus exists
    // compute average monthly income and expense
    $incomeStmt = $conn->prepare("
        SELECT SUM(amount) as total_income
        FROM transactions t
        JOIN accounts a ON t.account_id = a.id
        WHERE a.user_id = ? AND t.type = 'income' AND t.date >= ?
    ");
    $incomeStmt->execute([$user_id, $start]);
    $incomeRow = $incomeStmt->fetch(PDO::FETCH_ASSOC);
    $income = $incomeRow['total_income'] ? (float)$incomeRow['total_income'] : 0.0;

    if ($income > 0 && $total > 0) {
        $surplus = $income - $total;
        if ($surplus > 0) {
            $analysis['recommendations'][] = "Estimated surplus over the last {$months} months: ".round($surplus,2).". Consider allocating part of this to a savings goal.";
        } else {
            $analysis['recommendations'][] = "You're running at a monthly deficit or breaking even; review top expense categories to free up cash.";
        }
    }

    // Specific category advice: flag any single category >30% of total expenses
    foreach($cats as $c) {
        if ($total > 0 && ((float)$c['total'] / $total) * 100 > 30) {
            $analysis['recommendations'][] = "Category '{$c['category']}' accounts for more than 30% of spending. Review it for possible reductions or negotiate better rates.";
        }
    }

    return $analysis;
}
