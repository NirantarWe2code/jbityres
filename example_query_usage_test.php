<?php
/**
 * Example: How to use Query Logging in your code
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/SalesData.php';

echo "<h1>📝 Example: Query Logging Usage</h1>";

try {
    // Get database instance
    $db = Database::getInstance();
    
    echo "<h3>Example 1: Basic Query Debugging</h3>";
    
    // Execute a query
    $users = $db->fetchAll("SELECT id, username, role FROM users WHERE status = ?", ['active']);
    
    // Debug the last query
    echo "<h4>Method 1: getLastQueryFormatted()</h4>";
    echo "<strong>Last Query:</strong> <code>" . htmlspecialchars($db->getLastQueryFormatted()) . "</code><br>";
    
    echo "<h4>Method 2: debugLastQuery()</h4>";
    $db->debugLastQuery();
    
    echo "<h3>Example 2: Using with Auth Class</h3>";
    
    $auth = new Auth();
    
    // Try to get a user (this will execute SQL internally)
    $user = $auth->getUserById(1);
    
    echo "<h4>After Auth->getUserById(1):</h4>";
    echo "<strong>Query executed by Auth class:</strong><br>";
    $db->debugLastQuery();
    
    echo "<h3>Example 3: Using with SalesData Class</h3>";
    
    $salesData = new SalesData();
    
    // Get sales records (this will execute SQL internally)
    $result = $salesData->getAllRecords(1, 5);
    
    echo "<h4>After SalesData->getAllRecords(1, 5):</h4>";
    echo "<strong>Query executed by SalesData class:</strong><br>";
    $db->debugLastQuery();
    
    echo "<h3>Example 4: Query Statistics for Performance Monitoring</h3>";
    
    // Execute a few more queries for demonstration
    $db->fetchOne("SELECT COUNT(*) as total FROM users");
    $db->fetchOne("SELECT COUNT(*) as total FROM sales_data");
    $db->fetchAll("SELECT DISTINCT role FROM users");
    
    $stats = $db->getQueryStats();
    
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 4px;'>";
    echo "<h4>Current Session Query Statistics:</h4>";
    echo "<ul>";
    echo "<li><strong>Total Queries:</strong> " . $stats['total_queries'] . "</li>";
    echo "<li><strong>SELECT Queries:</strong> " . $stats['select_queries'] . "</li>";
    echo "<li><strong>INSERT Queries:</strong> " . $stats['insert_queries'] . "</li>";
    echo "<li><strong>UPDATE Queries:</strong> " . $stats['update_queries'] . "</li>";
    echo "<li><strong>DELETE Queries:</strong> " . $stats['delete_queries'] . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>Example 5: Recent Queries Log</h3>";
    
    $recentQueries = $db->getRecentQueries(5);
    
    echo "<h4>Last 5 Queries Executed:</h4>";
    echo "<ol>";
    foreach ($recentQueries as $query) {
        echo "<li>";
        echo "<strong>Time:</strong> " . $query['datetime'] . "<br>";
        echo "<strong>SQL:</strong> <code>" . htmlspecialchars($query['sql']) . "</code><br>";
        if (!empty($query['params'])) {
            echo "<strong>Params:</strong> <code>" . json_encode($query['params']) . "</code><br>";
        }
        echo "</li><br>";
    }
    echo "</ol>";
    
    echo "<h3>Example 6: Error Debugging</h3>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px;'>";
    echo "<h4>When you encounter database errors:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
    echo htmlspecialchars('try {
    $result = $db->fetchAll("SELECT * FROM some_table WHERE id = ?", [$id]);
    // Process result...
} catch (Exception $e) {
    // Debug the failed query
    echo "Error: " . $e->getMessage();
    echo "Last query: " . $db->getLastQueryFormatted();
    
    // Or use the debug method
    $db->debugLastQuery();
}');
    echo "</pre>";
    echo "</div>";
    
    echo "<h3>Example 7: Performance Monitoring</h3>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 4px;'>";
    echo "<h4>Monitor slow queries or query patterns:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
    echo htmlspecialchars('// At the end of your script or in a monitoring function
$stats = $db->getQueryStats();

if ($stats["total_queries"] > 50) {
    error_log("High query count detected: " . $stats["total_queries"]);
}

// Log recent queries for analysis
$recentQueries = $db->getRecentQueries(10);
foreach ($recentQueries as $query) {
    if (strpos($query["sql"], "SELECT") === 0 && count($query["params"]) == 0) {
        error_log("Potential N+1 query detected: " . $query["sql"]);
    }
}');
    echo "</pre>";
    echo "</div>";
    
    echo "<h3>Example 8: AJAX Debugging</h3>";
    
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px;'>";
    echo "<h4>Debug AJAX requests that fail:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
    echo htmlspecialchars('// In your AJAX handler (e.g., ajax/sales.php)
try {
    $salesData = new SalesData();
    $result = $salesData->getAllRecords($page, $limit, $filters);
    
    if (!$result["success"]) {
        // Include last query in error response for debugging
        $db = Database::getInstance();
        error_log("Sales query failed. Last query: " . $db->getLastQueryFormatted());
    }
    
    jsonResponse($result["success"], $result["message"], $result["data"]);
    
} catch (Exception $e) {
    $db = Database::getInstance();
    error_log("AJAX Error: " . $e->getMessage());
    error_log("Last Query: " . $db->getLastQueryFormatted());
    
    jsonResponse(false, "Database error occurred");
}');
    echo "</pre>";
    echo "</div>";
    
    echo "<h3>✅ Usage Examples Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>💡 Pro Tips:</h4>";
    echo "<ul>";
    echo "<li><strong>Development:</strong> Use <code>debugLastQuery()</code> to quickly see what SQL was executed</li>";
    echo "<li><strong>Production:</strong> Use <code>getLastQueryFormatted()</code> with error logging</li>";
    echo "<li><strong>Performance:</strong> Monitor <code>getQueryStats()</code> for query count optimization</li>";
    echo "<li><strong>Debugging:</strong> Use <code>getRecentQueries()</code> to trace query sequences</li>";
    echo "<li><strong>Memory:</strong> Use <code>clearQueryLog()</code> in long-running scripts</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    
    // Even in error cases, you can debug the last query
    $db = Database::getInstance();
    echo "<h4>Last Query Before Error:</h4>";
    $db->debugLastQuery();
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
pre { background: #f1f3f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 12px; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>