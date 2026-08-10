<?php
// export.php - Export all data as CSV
$db = new PDO('sqlite:tokens.db');
$result = $db->query("SELECT * FROM tokens ORDER BY timestamp DESC");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="tokens_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array_keys($rows[0] ?? []));
foreach ($rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
?>