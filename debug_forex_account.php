<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Not logged in");

$conn = new PDO("mysql:host=localhost;dbname=wallet_db", "root", "");
$user_id = $_SESSION['user_id'];

echo "<h2>Forex Account Debug</h2>";
echo "<hr>";

// Check trades
echo "<h3>1. Forex Trades</h3>";
$trades = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(profit_loss), 0) as total FROM forex_trades WHERE user_id = ?");
$trades->execute([$user_id]);
$t = $trades->fetch(PDO::FETCH_ASSOC);

echo "<p>Total Trades: <strong>{$t['count']}</strong></p>";
echo "<p>Total P/L: <strong>\${$t['total']}</strong></p>";

// Check Forex Account
echo "<h3>2. Forex Account in Database</h3>";
$acc = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND name = 'Forex Account'");
$acc->execute([$user_id]);
$account = $acc->fetch(PDO::FETCH_ASSOC);

if ($account) {
    echo "<p style='color:green'>✅ Forex Account EXISTS</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Balance</th><th>Currency</th></tr>";
    echo "<tr>";
    echo "<td>{$account['id']}</td>";
    echo "<td>{$account['name']}</td>";
    echo "<td>{$account['type']}</td>";
    echo "<td><strong>{$account['balance']}</strong></td>";
    echo "<td>{$account['currency']}</td>";
    echo "</tr>";
    echo "</table>";
    
    if ($account['balance'] != $t['total']) {
        echo "<p style='color:orange'>⚠️ Balance mismatch! Should be: \${$t['total']}</p>";
        echo "<form method='post'>";
        echo "<button type='submit' name='fix_balance'>Fix Balance Now</button>";
        echo "</form>";
        
        if (isset($_POST['fix_balance'])) {
            $update = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
            $update->execute([$t['total'], $account['id']]);
            echo "<p style='color:green'>✅ Balance updated to \${$t['total']}!</p>";
            echo "<p><a href='/MY CASH/pages/accounts.php'>View Accounts Page</a></p>";
        }
    } else {
        echo "<p style='color:green'>✅ Balance is correct!</p>";
    }
} else {
    echo "<p style='color:red'>❌ Forex Account DOES NOT EXIST</p>";
    
    if ($t['count'] > 0) {
        echo "<p>You have trades but no account. Creating it now...</p>";
        $create = $conn->prepare("INSERT INTO accounts (user_id, name, type, balance, currency) VALUES (?, 'Forex Account', 'Forex', ?, 'USD')");
        $create->execute([$user_id, $t['total']]);
        echo "<p style='color:green'>✅ Forex Account created with balance: \${$t['total']}</p>";
        echo "<p><a href='/MY CASH/pages/accounts.php'>View Accounts Page</a></p>";
    } else {
        echo "<p>No trades yet. Add a trade first, and the account will be created automatically.</p>";
        echo "<p><a href='/MY CASH/pages/forex_journal.php'>Go to Forex Journal</a></p>";
    }
}
?>
