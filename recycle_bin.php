<?php
session_start();
if (!isset($_SESSION["user_id"])) {
   header("Location: login.php");
   exit;
}
require_once "db.php";
function e($value) {
   return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
   $id = (int)($_POST["order_id"] ?? 0);
   $stmt = $pdo->prepare(
       "UPDATE orders
        SET is_deleted = 0, deleted_by = NULL, deleted_at = NULL,
            updated_by = ?
        WHERE order_id = ?"
   );
   $stmt->execute([$_SESSION["user_id"], $id]);
   header("Location: recycle_bin.php");
   exit;
}

$rows = $pdo->query(
   "SELECT orders.*, cafe.cafe_name, d.username AS deleted_by_name
    FROM orders
    INNER JOIN cafe ON orders.cafe_id = cafe.cafe_id
    LEFT JOIN users d ON orders.deleted_by = d.user_id
    WHERE orders.is_deleted = 1
    ORDER BY orders.deleted_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Recycle Bin</title>
   <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
   <h1>Recycle Bin</h1>
   <p>Deleted Orders</p>
</header>
<nav>
   <a href="index.php">Home</a>
   <a href="cafe.php">Manage Cafes</a>
   <a href="orders.php">Manage Orders</a>
   <a href="recycle_bin.php">Recycle Bin</a>
   <a href="logout.php">Logout (<?= e($_SESSION["username"]) ?>)</a>
</nav>
<main class="container">
   <h2>Deleted Order Records</h2>
   <div class="table-wrap">
       <table>
           <tr>
               <th>ID</th>
               <th>Date</th>
               <th>Customer</th>
               <th>Amount</th>
               <th>Cafe</th>
               <th>Image</th>
               <th>Deleted By</th>
               <th>Deleted At</th>
               <th>Action</th>
           </tr>
           <?php foreach ($rows as $r): ?>
           <tr>
               <td><?= e($r["order_id"]) ?></td>
               <td><?= e($r["order_date"]) ?></td>
               <td><?= e($r["customer_name"]) ?></td>
               <td>₱<?= number_format((float)$r["total_amount"], 2) ?></td>
               <td><?= e($r["cafe_name"]) ?></td>
               <td>
                   <?php if (!empty($r["image"])): ?>
                       <img src="uploads/<?= e($r["image"]) ?>" width="70" alt="">
                   <?php endif; ?>
               </td>
               <td><?= e($r["deleted_by_name"] ?? "-") ?></td>
               <td><?= e($r["deleted_at"]) ?></td>
               <td>
                   <form method="POST"
                         onsubmit="return confirm('Restore this order?');">
                       <input type="hidden" name="order_id"
                              value="<?= e($r["order_id"]) ?>">
                       <button class="small" type="submit">Restore</button>
                   </form>
               </td>
           </tr>
           <?php endforeach; ?>
       </table>
   </div>
</main>
</body>
</html>
