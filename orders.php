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
$userId = $_SESSION["user_id"];
$editOrder = [
   "order_id" => "",
   "order_date" => date("Y-m-d"),
   "customer_name" => "",
   "total_amount" => "",
   "cafe_id" => ""
];
if (isset($_GET["edit"])) {
   $stmt = $pdo->prepare(
       "SELECT * FROM orders WHERE order_id = ? AND is_deleted = 0"
   );
   $stmt->execute([(int)$_GET["edit"]]);
   $foundOrder = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($foundOrder) {
       $editOrder = $foundOrder;
   }
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
   $action = $_POST["action"] ?? "";
   if ($action === "save") {
       $id = (int)($_POST["order_id"] ?? 0);
       $date = $_POST["order_date"] ?? "";
       $customer = trim($_POST["customer_name"] ?? "");
       $amount = $_POST["total_amount"] ?? "";
       $cafeId = (int)($_POST["cafe_id"] ?? 0);
       $imageName = null;
       if (!empty($_FILES["image"]["name"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
           $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
           if (in_array($ext, ["jpg", "jpeg", "png", "gif"])) {
               $imageName = uniqid("order_") . "." . $ext;
               move_uploaded_file($_FILES["image"]["tmp_name"], "uploads/" . $imageName);
           }
       }
       $validDate = DateTime::createFromFormat("!Y-m-d", $date);
       $validDate = $validDate && $validDate->format("Y-m-d") === $date;
       $validAmount = is_numeric($amount) && (float)$amount >= 0;
       $checkCafe = $pdo->prepare(
           "SELECT COUNT(*) FROM cafe WHERE cafe_id = ?"
       );
       $checkCafe->execute([$cafeId]);
       if (
           $validDate &&
           $customer !== "" &&
           $validAmount &&
           $checkCafe->fetchColumn() > 0
       ) {
           if ($id > 0) {
               $stmt = $pdo->prepare(
                   "UPDATE orders
                    SET order_date = ?, customer_name = ?,
                        total_amount = ?, cafe_id = ?, updated_by = ?,
                        image = COALESCE(?, image)
                    WHERE order_id = ?"
               );
               $stmt->execute([
                   $date, $customer, $amount, $cafeId, $userId, $imageName, $id
               ]);
           } else {
               $stmt = $pdo->prepare(
                   "INSERT INTO orders
                    (order_date, customer_name, total_amount, cafe_id,
                     added_by, updated_by, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
               );
               $stmt->execute([
                   $date, $customer, $amount, $cafeId, $userId, $userId, $imageName
               ]);
           }
       }
       header("Location: orders.php");
       exit;
   }
   if ($action === "delete") {
       $id = (int)($_POST["order_id"] ?? 0);
       $stmt = $pdo->prepare(
           "UPDATE orders
            SET is_deleted = 1, deleted_by = ?, deleted_at = NOW()
            WHERE order_id = ?"
       );
       $stmt->execute([$userId, $id]);
       header("Location: orders.php");
       exit;
   }
}
$cafes = $pdo->query(
   "SELECT * FROM cafe ORDER BY cafe_name"
)->fetchAll(PDO::FETCH_ASSOC);
$search = trim($_GET["search"] ?? "");
$sql = "SELECT orders.*, cafe.cafe_name,
               a.username AS added_by_name,
               u.username AS updated_by_name
        FROM orders
        INNER JOIN cafe ON orders.cafe_id = cafe.cafe_id
        LEFT JOIN users a ON orders.added_by = a.user_id
        LEFT JOIN users u ON orders.updated_by = u.user_id
        WHERE orders.is_deleted = 0";
$params = [];
if ($search !== "") {
    $sql .= " AND (orders.customer_name LIKE ?
              OR cafe.cafe_name LIKE ?
              OR orders.order_date LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY orders.order_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Manage Orders</title>
   <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
   <h1>Manage Orders</h1>
   <p>Child Entity</p>
</header>
<nav>
   <a href="index.php">Home</a>
   <a href="cafe.php">Manage Cafes</a>
   <a href="orders.php">Manage Orders</a>
   <a href="recycle_bin.php">Recycle Bin</a>
   <a href="logout.php">Logout (<?= e($_SESSION["username"]) ?>)</a>
</nav>
<main class="container">
   <h2><?= $editOrder["order_id"] ? "Update Order" : "Add an Order" ?></h2>
   <?php if (count($cafes) === 0): ?>
       <p>Please add a cafe first before creating an order.</p>
       <a class="button" href="cafe.php">Add a Cafe</a>
   <?php else: ?>
   <form method="POST" enctype="multipart/form-data" class="form-card">
       <input type="hidden" name="action" value="save">
       <input type="hidden" name="order_id"
              value="<?= e($editOrder["order_id"]) ?>">
       <label>Order Date</label>
       <input type="date" name="order_date"
              value="<?= e($editOrder["order_date"]) ?>" required>
       <label>Customer Name</label>
       <input type="text" name="customer_name"
              value="<?= e($editOrder["customer_name"]) ?>" required>
       <label>Total Amount (₱)</label>
       <input type="number" name="total_amount"
              min="0" step="0.01"
              value="<?= e($editOrder["total_amount"]) ?>" required>
       <label>Cafe</label>
       <select name="cafe_id" required>
           <option value="">Select a cafe</option>
           <?php foreach ($cafes as $cafe): ?>
               <option value="<?= e($cafe["cafe_id"]) ?>"
                   <?= (string)$editOrder["cafe_id"] ===
                       (string)$cafe["cafe_id"] ? "selected" : "" ?>>
                   <?= e($cafe["cafe_name"]) ?>
               </option>
           <?php endforeach; ?>
       </select>
       <label>Order Image (issue/photo)</label>
       <input type="file" name="image" accept="image/*"
              <?= $editOrder["order_id"] ? "" : "required" ?>>
       <button type="submit">
           <?= $editOrder["order_id"] ? "Update Order" : "Add Order" ?>
       </button>
       <?php if ($editOrder["order_id"]): ?>
           <a class="button secondary" href="orders.php">Cancel</a>
       <?php endif; ?>
   </form>
   <?php endif; ?>
   <form method="GET" class="form-card">
       <label>Search Order</label>
       <input type="text" name="search" value="<?= e($search) ?>"
              placeholder="Search by customer, cafe, or date">
       <button type="submit">Search</button>
       <?php if ($search !== ""): ?>
           <a class="button secondary" href="orders.php">Clear</a>
       <?php endif; ?>
   </form>
   <h2>Order Records</h2>
   <div class="table-wrap">
       <table>
           <tr>
               <th>ID</th>
               <th>Date</th>
               <th>Customer</th>
               <th>Amount</th>
               <th>Cafe</th>
               <th>Date Added</th>
               <th>Added By</th>
               <th>Last Updated By</th>
               <th>Last Updated</th>
               <th>Image</th>
               <th>Actions</th>
           </tr>
           <?php foreach ($orders as $order): ?>
           <tr>
               <td><?= e($order["order_id"]) ?></td>
               <td><?= e($order["order_date"]) ?></td>
               <td><?= e($order["customer_name"]) ?></td>
               <td>₱<?= number_format((float)$order["total_amount"], 2) ?></td>
               <td><?= e($order["cafe_name"]) ?></td>
               <td><?= e($order["date_added"]) ?></td>
               <td><?= e($order["added_by_name"] ?? "-") ?></td>
               <td><?= e($order["updated_by_name"] ?? "-") ?></td>
               <td><?= e($order["last_updated"]) ?></td>
               <td>
                   <?php if (!empty($order["image"])): ?>
                       <img src="uploads/<?= e($order["image"]) ?>"
                            width="70" alt="Order image">
                   <?php endif; ?>
               </td>
               <td class="actions">
                   <a class="button small"
                      href="orders.php?edit=<?= e($order["order_id"]) ?>">
                       Edit
                   </a>
                   <form method="POST"
                         onsubmit="return confirm('Delete this order?');">
                       <input type="hidden" name="action" value="delete">
                       <input type="hidden" name="order_id"
                              value="<?= e($order["order_id"]) ?>">
                       <button class="danger small" type="submit">
                           Delete
                       </button>
                   </form>
               </td>
           </tr>
           <?php endforeach; ?>
       </table>
   </div>
</main>
</body>
</html>
