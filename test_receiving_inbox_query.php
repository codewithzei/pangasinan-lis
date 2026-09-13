<?php
/**
 * Test script to verify Receiving Inbox queries work with ONLY_FULL_GROUP_BY
 * 
 * This script tests the refactored queries to ensure they comply with MySQL's
 * ONLY_FULL_GROUP_BY requirement.
 */

require_once __DIR__ . '/app/config/database.php';

echo "=== Testing Receiving Inbox Queries ===\n\n";

try {
    $database = new Database();
    $pdo = $database->connect();
    
    // Check current SQL mode
    $modeStmt = $pdo->query("SELECT @@sql_mode AS sql_mode");
    $currentMode = $modeStmt->fetch()['sql_mode'];
    echo "Current SQL Mode: {$currentMode}\n\n";
    
    // Ensure ONLY_FULL_GROUP_BY is enabled
    $hasOnlyFullGroupBy = strpos($currentMode, 'ONLY_FULL_GROUP_BY') !== false;
    if (!$hasOnlyFullGroupBy) {
        echo "⚠️  WARNING: ONLY_FULL_GROUP_BY is not currently enabled.\n";
        echo "Enabling it for this test...\n";
        $pdo->exec("SET sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
        echo "✓ ONLY_FULL_GROUP_BY enabled for this session.\n\n";
    } else {
        echo "✓ ONLY_FULL_GROUP_BY is enabled.\n\n";
    }
    
    // Get Receiving role ID
    $roleStmt = $pdo->query("
        SELECT id FROM roles 
        WHERE name = 'Receiving Staff' AND is_active = 1 AND is_deleted = 0 
        LIMIT 1
    ");
    $receivingRole = $roleStmt->fetch();
    
    if (!$receivingRole) {
        echo "❌ ERROR: Receiving Staff role not found.\n";
        exit(1);
    }
    
    $receivingRoleId = (int)$receivingRole['id'];
    echo "Receiving Role ID: {$receivingRoleId}\n\n";
    
    // Test 1: Returned Documents Query
    echo "--- Test 1: Returned Documents Query ---\n";
    
    $where = [
        'da.assigned_to_role_id = ?',
        "da.phase = 'RECEIVING'",
        "da.decision = 'PENDING'",
        'da.completed_at IS NULL',
        "EXISTS (
            SELECT 1 FROM document_assignments da_admin
            WHERE da_admin.document_id = da.document_id
              AND da_admin.phase = 'ADMIN'
              AND da_admin.decision = 'DECLINED'
              AND da_admin.declined_at IS NOT NULL
        )"
    ];
    $whereClause = implode(' AND ', $where);
    
    $returnedQuery = "
        SELECT
            da.id              AS assignment_id,
            da.received_at,
            da.decision,
            d.id               AS document_id,
            d.tracking_number,
            d.subject_matter,
            d.document_type_id,
            dt.name            AS document_type_name,
            dt.badge_color     AS document_type_badge_color,
            d.current_phase,
            d.date_received,
            d.time_received,
            ds.name            AS status,
            ds.badge_color     AS status_badge_color,
            st.name            AS source_type,
            COALESCE(eo.name, h.name, m.name, d.source_name, '—') AS source_display,
            da_admin.decline_reason,
            da_admin.declined_at,
            ua_declined.username AS declined_by_username,
            CONCAT(ui_declined.first_name, ' ', ui_declined.last_name) AS declined_by_name
        FROM document_assignments da
        INNER JOIN documents         d  ON da.document_id      = d.id
        LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
        LEFT  JOIN document_types    dt ON d.document_type_id  = dt.id
        LEFT  JOIN source_types      st ON d.source_type_id    = st.id
        LEFT  JOIN external_offices  eo ON d.external_office_id = eo.id
        LEFT  JOIN hospitals          h ON d.hospital_id        = h.id
        LEFT  JOIN municities         m ON d.municipality_id    = m.id
        LEFT  JOIN (
            -- Get the latest declined Admin assignment ID per document
            SELECT da_latest.document_id, da_latest.id AS latest_id
            FROM document_assignments da_latest
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_id
                FROM document_assignments
                WHERE phase = 'ADMIN' AND decision = 'DECLINED' AND declined_at IS NOT NULL
                GROUP BY document_id
            ) da_max ON da_latest.document_id = da_max.document_id AND da_latest.id = da_max.max_id
        ) latest_decline ON latest_decline.document_id = da.document_id
        LEFT  JOIN document_assignments da_admin ON da_admin.id = latest_decline.latest_id
        LEFT  JOIN user_accounts ua_declined ON da_admin.assigned_by = ua_declined.id
        LEFT  JOIN user_info ui_declined ON ua_declined.id = ui_declined.user_account_id
        WHERE {$whereClause}
        ORDER BY da.received_at DESC, d.date_received DESC, d.id DESC
        LIMIT 5
    ";
    
    try {
        $stmt = $pdo->prepare($returnedQuery);
        $stmt->execute([$receivingRoleId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✓ Query executed successfully!\n";
        echo "  Found " . count($results) . " returned document(s).\n";
        
        if (count($results) > 0) {
            echo "\nSample Result:\n";
            $first = $results[0];
            echo "  - Document ID: {$first['document_id']}\n";
            echo "  - Tracking Number: {$first['tracking_number']}\n";
            echo "  - Subject: " . substr($first['subject_matter'], 0, 50) . "...\n";
            echo "  - Declined By: {$first['declined_by_name']} ({$first['declined_by_username']})\n";
            echo "  - Declined At: {$first['declined_at']}\n";
            echo "  - Decline Reason: " . substr($first['decline_reason'] ?? 'N/A', 0, 50) . "...\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ ERROR: Query failed!\n";
        echo "   " . $e->getMessage() . "\n";
        exit(1);
    }
    
    echo "\n";
    
    // Test 2: Accepted Documents Query
    echo "--- Test 2: Accepted Documents Query ---\n";
    
    $where = [
        'da.assigned_to_role_id = ?',
        "da.phase = 'RECEIVING'",
        "da.decision IN ('ACCEPTED', 'COMPLETED')",
        'da.completed_at IS NOT NULL',
        "EXISTS (
            SELECT 1 FROM document_assignments da_admin
            WHERE da_admin.document_id = da.document_id
              AND da_admin.phase = 'ADMIN'
              AND da_admin.decision = 'DECLINED'
        )"
    ];
    $whereClause = implode(' AND ', $where);
    
    $acceptedQuery = "
        SELECT
            da.id              AS assignment_id,
            da.received_at,
            da.completed_at,
            da.decision,
            d.id               AS document_id,
            d.tracking_number,
            d.subject_matter,
            d.document_type_id,
            dt.name            AS document_type_name,
            dt.badge_color     AS document_type_badge_color,
            d.current_phase,
            d.date_received,
            d.time_received,
            ds.name            AS status,
            ds.badge_color     AS status_badge_color,
            st.name            AS source_type,
            COALESCE(eo.name, h.name, m.name, d.source_name, '—') AS source_display,
            ua_accepted.username AS accepted_by_username,
            CONCAT(ui_accepted.first_name, ' ', ui_accepted.last_name) AS accepted_by_name
        FROM document_assignments da
        INNER JOIN documents         d  ON da.document_id      = d.id
        LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
        LEFT  JOIN document_types    dt ON d.document_type_id  = dt.id
        LEFT  JOIN source_types      st ON d.source_type_id    = st.id
        LEFT  JOIN external_offices  eo ON d.external_office_id = eo.id
        LEFT  JOIN hospitals          h ON d.hospital_id        = h.id
        LEFT  JOIN municities         m ON d.municipality_id    = m.id
        LEFT  JOIN user_accounts ua_accepted ON da.assigned_by = ua_accepted.id
        LEFT  JOIN user_info ui_accepted ON ua_accepted.id = ui_accepted.user_account_id
        WHERE {$whereClause}
        ORDER BY da.completed_at DESC, d.date_received DESC, d.id DESC
        LIMIT 5
    ";
    
    try {
        $stmt = $pdo->prepare($acceptedQuery);
        $stmt->execute([$receivingRoleId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✓ Query executed successfully!\n";
        echo "  Found " . count($results) . " accepted document(s).\n";
        
        if (count($results) > 0) {
            echo "\nSample Result:\n";
            $first = $results[0];
            echo "  - Document ID: {$first['document_id']}\n";
            echo "  - Tracking Number: {$first['tracking_number']}\n";
            echo "  - Subject: " . substr($first['subject_matter'], 0, 50) . "...\n";
            echo "  - Accepted By: {$first['accepted_by_name']} ({$first['accepted_by_username']})\n";
            echo "  - Completed At: {$first['completed_at']}\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ ERROR: Query failed!\n";
        echo "   " . $e->getMessage() . "\n";
        exit(1);
    }
    
    echo "\n";
    
    // Test 3: Statistics Query
    echo "--- Test 3: Statistics Query ---\n";
    
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT CASE 
                WHEN da.decision = 'PENDING' 
                AND da.completed_at IS NULL 
                AND EXISTS (
                    SELECT 1 FROM document_assignments da2
                    WHERE da2.document_id = da.document_id
                      AND da2.phase = 'ADMIN'
                      AND da2.decision = 'DECLINED'
                ) 
                THEN da.document_id 
            END) AS returned_count,
            COUNT(DISTINCT CASE 
                WHEN da.decision IN ('ACCEPTED', 'COMPLETED') 
                AND da.completed_at IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM document_assignments da2
                    WHERE da2.document_id = da.document_id
                      AND da2.phase = 'ADMIN'
                      AND da2.decision = 'DECLINED'
                )
                THEN da.document_id 
            END) AS accepted_count
        FROM document_assignments da
        WHERE da.assigned_to_role_id = ? AND da.phase = 'RECEIVING'
    ";
    
    try {
        $stmt = $pdo->prepare($statsQuery);
        $stmt->execute([$receivingRoleId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "✓ Query executed successfully!\n";
        echo "  - Returned Count: {$stats['returned_count']}\n";
        echo "  - Accepted Count: {$stats['accepted_count']}\n";
        
    } catch (PDOException $e) {
        echo "❌ ERROR: Query failed!\n";
        echo "   " . $e->getMessage() . "\n";
        exit(1);
    }
    
    echo "\n";
    echo "=== All Tests Passed! ✓ ===\n";
    echo "\nThe Receiving Inbox queries are compliant with ONLY_FULL_GROUP_BY.\n";
    
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
