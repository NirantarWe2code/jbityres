<?php
/**
 * Test Query Logging Functionality
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<h1>🔍 Test Query Logging Functionality</h1>";

try {
    $db = Database::getInstance();
    
    echo "<h3>1. Basic Query Logging Test</h3>";
    
    // Test some queries
    echo "<h4>Executing test queries...</h4>";
    
    // Query 1: Get users count
    $result1 = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "✅ Query 1 executed: Get users count<br>";
    echo "<strong>Last Query:</strong> " . $db->getLastQueryFormatted() . "<br><br>";
    
    // Query 2: Get specific user
    $result2 = $db->fetchOne("SELECT username, full_name FROM users WHERE role = ? LIMIT 1", ['admin']);
    echo "✅ Query 2 executed: Get admin user<br>";
    echo "<strong>Last Query:</strong> " . $db->getLastQueryFormatted() . "<br><br>";
    
    // Query 3: Get sales data count
    $result3 = $db->fetchOne("SELECT COUNT(*) as count FROM sales_data WHERE dated >= ?", ['2024-01-01']);
    echo "✅ Query 3 executed: Get sales data count<br>";
    echo "<strong>Last Query:</strong> " . $db->getLastQueryFormatted() . "<br><br>";
    
    echo "<h3>2. Query Log Analysis</h3>";
    
    // Get recent queries
    $recentQueries = $db->getRecentQueries(5);
    echo "<h4>Recent Queries (Last 5):</h4>";
    
    if (!empty($recentQueries)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 8px;'>#</th>";
        echo "<th style='padding: 8px;'>Time</th>";
        echo "<th style='padding: 8px;'>Query</th>";
        echo "<th style='padding: 8px;'>Parameters</th>";
        echo "</tr>";
        
        foreach ($recentQueries as $index => $query) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . ($index + 1) . "</td>";
            echo "<td style='padding: 8px;'>" . $query['datetime'] . "</td>";
            echo "<td style='padding: 8px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($query['sql']) . "</td>";
            echo "<td style='padding: 8px;'>" . json_encode($query['params']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "No queries found in log.<br>";
    }
    
    echo "<h3>3. Query Statistics</h3>";
    
    $stats = $db->getQueryStats();
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
    echo "<h4>Query Statistics:</h4>";
    echo "<ul>";
    echo "<li><strong>Total Queries:</strong> " . $stats['total_queries'] . "</li>";
    echo "<li><strong>SELECT Queries:</strong> " . $stats['select_queries'] . "</li>";
    echo "<li><strong>INSERT Queries:</strong> " . $stats['insert_queries'] . "</li>";
    echo "<li><strong>UPDATE Queries:</strong> " . $stats['update_queries'] . "</li>";
    echo "<li><strong>DELETE Queries:</strong> " . $stats['delete_queries'] . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>4. Debug Last Query Function</h3>";
    
    // Execute one more query for demonstration
    $db->fetchAll("SELECT id, username, role FROM users WHERE status = ? ORDER BY created_at DESC LIMIT ?", ['active', 3]);
    
    echo "<h4>Using debugLastQuery() method:</h4>";
    $db->debugLastQuery();
    
    echo "<h3>5. Individual Method Tests</h3>";
    
    echo "<h4>getLastQuery():</h4>";
    echo "<code>" . htmlspecialchars($db->getLastQuery()) . "</code><br><br>";
    
    echo "<h4>getLastParams():</h4>";
    echo "<code>" . json_encode($db->getLastParams()) . "</code><br><br>";
    
    echo "<h4>getLastQueryFormatted():</h4>";
    echo "<code>" . htmlspecialchars($db->getLastQueryFormatted()) . "</code><br><br>";
    
    echo "<h3>6. Practical Usage Examples</h3>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
    echo "<h4>How to use in your code:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
    echo htmlspecialchars('// Get database instance
$db = Database::getInstance();

// Execute your query
$users = $db->fetchAll("SELECT * FROM users WHERE role = ?", ["admin"]);

// Debug the last query
echo "Last executed query: " . $db->getLastQueryFormatted();

// Or use the debug method
$db->debugLastQuery();

// Get query statistics
$stats = $db->getQueryStats();
echo "Total queries executed: " . $stats["total_queries"];

// Get recent queries for debugging
$recent = $db->getRecentQueries(10);
foreach ($recent as $query) {
    echo $query["datetime"] . ": " . $query["sql"];
}');
    echo "</pre>";
    echo "</div>";
    
    echo "<h3>7. Query Logging Control</h3>";
    
    echo "<h4>Testing query logging on/off:</h4>";
    
    // Disable logging
    $db->setQueryLogging(false);
    $db->fetchOne("SELECT 1 as test"); // This won't be logged
    echo "✅ Query executed with logging disabled<br>";
    
    // Enable logging
    $db->setQueryLogging(true);
    $db->fetchOne("SELECT 2 as test"); // This will be logged
    echo "✅ Query executed with logging enabled<br>";
    
    echo "<strong>Current last query:</strong> " . $db->getLastQueryFormatted() . "<br>";
    
    echo "<h3>8. Clear Query Log</h3>";
    
    echo "<h4>Before clearing:</h4>";
    echo "Total queries in log: " . count($db->getQueryLog()) . "<br>";
    
    // Clear the log
    $db->clearQueryLog();
    
    echo "<h4>After clearing:</h4>";
    echo "Total queries in log: " . count($db->getQueryLog()) . "<br>";
    echo "Last query: " . ($db->getLastQuery() ?: 'None') . "<br>";
    
    echo "<h3>✅ All Tests Completed Successfully!</h3>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>New Database Methods Available:</h4>";
    echo "<ul>";
    echo "<li><code>getLastQuery()</code> - Get the raw SQL of last executed query</li>";
    echo "<li><code>getLastParams()</code> - Get parameters of last executed query</li>";
    echo "<li><code>getLastQueryFormatted()</code> - Get formatted query with parameters substituted</li>";
    echo "<li><code>getQueryLog()</code> - Get all logged queries</li>";
    echo "<li><code>getRecentQueries(\$limit)</code> - Get recent N queries</li>";
    echo "<li><code>clearQueryLog()</code> - Clear the query log</li>";
    echo "<li><code>setQueryLogging(\$enabled)</code> - Enable/disable query logging</li>";
    echo "<li><code>getQueryStats()</code> - Get query statistics</li>";
    echo "<li><code>debugLastQuery()</code> - Print formatted last query for debugging</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
pre { background: #f1f3f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 12px; }
table { background: white; }
th { font-weight: bold; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>