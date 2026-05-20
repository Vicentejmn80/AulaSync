<?php
// Test connection with different escaping
$password = 'Vjmn02110801.k$';

echo "Testing with escaped password...\n";

// Try with URL-encoded password
$encodedPass = urlencode($password);
echo "Encoded: $encodedPass\n";

$configs = [
    ['host' => 'aws-1-us-west-2.pooler.supabase.com', 'port' => 6543, 'user' => 'postgres.ruxwiztdildgnyshajnk', 'pass' => $password],
];

foreach ($configs as $cfg) {
    echo "\nTesting: {$cfg['host']}:{$cfg['port']} user={$cfg['user']}\n";

    try {
        $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname=postgres;sslmode=require";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
        ]);

        echo "SUCCESS!\n";

        $result = $pdo->query("SELECT conname, pg_get_constraintdef(oid) as def FROM pg_constraint WHERE conname = 'activities_type_check'");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        echo "Constraint: " . ($row ? $row['def'] : 'NOT FOUND') . "\n";

        // Update constraint to include 'tarea'
        echo "Updating constraint...\n";
        $pdo->exec('ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_type_check');
        $pdo->exec("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ('clase', 'actividad', 'tarea'))");
        echo "DONE!\n";

        break;

    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}