<?php
// clear.php - Delete all data (USE WITH CAUTION!)
$db = new PDO('sqlite:tokens.db');
$db->exec("DELETE FROM tokens");
header('Location: view_tokens.php');
?>