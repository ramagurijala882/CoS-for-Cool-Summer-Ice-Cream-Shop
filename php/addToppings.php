<?php
session_start();

require 'vendor/autoload.php'; // Include AWS SDK for PHP

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to retrieve database credentials from AWS Secrets Manager
function getDatabaseCredentials() {
    $client = new SecretsManagerClient([
        'region' => 'us-east-1', // Update with your AWS region
        'version' => 'latest'
    ]);

    try {
        $result = $client->getSecretValue([
            'SecretId' => 'ice_shop_db_credentials' // Replace with your secret name
        ]);

        if (isset($result['SecretString'])) {
            return json_decode($result['SecretString'], true);
        } else {
            throw new Exception("SecretString not found.");
        }
    } catch (AwsException $e) {
        die("Error retrieving secrets: " . $e->getMessage());
    }
}

$credentials = getDatabaseCredentials();

$servername = $credentials['servername'];
$username = $credentials['username'];
$password = $credentials['password'];
$dbname = $credentials['dbname'];

function getDatabaseConnection() {
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = getDatabaseConnection();

// Handle topping actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['addTopping'])) {
        $toppingName = $_POST['toppingName'];
        $toppingPrice = $_POST['toppingPrice'];

        $query = "INSERT INTO toppings (topping_name, price) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sd", $toppingName, $toppingPrice);
        $stmt->execute();
    }

    if (isset($_POST['updateTopping'])) {
        $toppingId = $_POST['toppingId'];
        $toppingName = $_POST['toppingName'];
        $toppingPrice = $_POST['toppingPrice'];

        $query = "UPDATE toppings SET topping_name=?, price=? WHERE topping_id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdi", $toppingName, $toppingPrice, $toppingId);
        $stmt->execute();
    }

    if (isset($_POST['deleteTopping'])) {
        $toppingId = $_POST['toppingId'];

        $query = "DELETE FROM toppings WHERE topping_id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $toppingId);
        $stmt->execute();
    }
}

// Fetch toppings
$toppings = $conn->query("SELECT * FROM toppings");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="container">
        <h2>Admin Panel - Manage Products</h2>
        
        <a href="admin.php">Products</a>

        <!-- Add Topping Button and Form -->
        <button onclick="toggleAddToppingForm()">Add Topping</button>

        <div id="add-topping-form" style="display: none;">
            <h3>Add Topping</h3>
            <form method="post">
                <label>Topping Name: <input type="text" name="toppingName" required></label>
                <label>Topping Price: <input type="number" step="0.01" name="toppingPrice" required></label>
                <button type="submit" name="addTopping">Add Topping</button>
            </form>
        </div>

        <h3>Toppings List</h3>
        <ul class="product-list">
            <?php while ($topping = $toppings->fetch_assoc()): ?>
                <li>
                    <strong><?= htmlspecialchars($topping['topping_name']) ?></strong> - 
                    $<?= htmlspecialchars($topping['price']) ?>
                    <button onclick="toggleEditToppingForm(<?= $topping['topping_id'] ?>)">Edit</button>
                    
                    <div id="edit-topping-form-<?= $topping['topping_id'] ?>" class="edit-form" style="display: none;">
                        <form method="post">
                            <input type="hidden" name="toppingId" value="<?= $topping['topping_id'] ?>">
                            <label>Topping Name: <input type="text" name="toppingName" value="<?= htmlspecialchars($topping['topping_name']) ?>" required></label>
                            <label>Topping Price: <input type="number" step="0.01" name="toppingPrice" value="<?= htmlspecialchars($topping['price']) ?>" required></label>
                            <button type="submit" name="updateTopping">Update</button>
                            <button type="submit" name="deleteTopping" class="delete-button" onclick="return confirm('Are you sure you want to delete this topping?')">Delete</button>
                        </form>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>

        <script>
            function toggleAddToppingForm() {
                const form = document.getElementById('add-topping-form');
                form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
            }

            function toggleEditToppingForm(toppingId) {
                const form = document.getElementById('edit-topping-form-' + toppingId);
                form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
            }
        </script>
</body>
</html>
