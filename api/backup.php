<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die('Unauthorized');
}

// Generate SQL Dump
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sql = "-- Tech With Kru.M Database Backup\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $row[1] . ";\n\n";
    
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        $sql .= "INSERT INTO `$table` VALUES \n";
        $values = [];
        foreach ($rows as $r) {
            $r_vals = array_map(function($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($r));
            $values[] = "(" . implode(", ", $r_vals) . ")";
        }
        $sql .= implode(",\n", $values) . ";\n\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="krum_backup_' . date('Y_m_d_His') . '.sql"');
header('Content-Length: ' . strlen($sql));
echo $sql;
exit;
