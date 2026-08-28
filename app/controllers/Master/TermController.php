<?php

require_once __DIR__ . '/../../config/database.php';

class TermController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $filterYear = $_GET['year'] ?? '';
        $filterStatus = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $whereClauses = ['t.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(t.name LIKE ? OR t.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterYear !== '') {
            $whereClauses[] = 't.year = ?';
            $params[] = (int)$filterYear;
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 't.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM terms t WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT t.*,
            (SELECT COUNT(*) FROM term_legislators tl WHERE tl.term_id = t.id) AS member_count
        FROM terms t
        WHERE {$whereSql}
        ORDER BY t.start_date DESC
        LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $terms = $stmt->fetchAll();

        $activeStmt = $this->pdo->prepare("SELECT * FROM terms WHERE is_deleted = 0 AND is_active = 1 LIMIT 1");
        $activeStmt->execute();
        $activeTerm = $activeStmt->fetch();

        $yearStmt = $this->pdo->query("SELECT DISTINCT year FROM terms WHERE is_deleted = 0 ORDER BY year DESC");
        $years = $yearStmt->fetchAll();

        $totalTerms = (int)$this->pdo->query("SELECT COUNT(*) FROM terms WHERE is_deleted = 0")->fetchColumn();
        $totalLegislators = (int)$this->pdo->query("SELECT COUNT(DISTINCT sp_member_id) FROM term_legislators")->fetchColumn();

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Legislative Terms Management';
        $pageSubtitle = 'Manage congressional sessions and SP member assignments';
        $accent = 'primary';

        require __DIR__ . '/../../../resources/views/master/legislative-terms/index.php';
    }

    public function store(): void
    {
        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'session_number' => (int)($_POST['session_number'] ?? 1),
            'year' => (int)($_POST['year'] ?? date('Y')),
            'start_date' => trim($_POST['start_date'] ?? ''),
            'end_date' => trim($_POST['end_date'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateTerm($data);
        $overlapError = $this->checkDateOverlap($data['start_date'], $data['end_date']);
        if ($overlapError) {
            $errors[] = $overlapError;
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/legislative-terms');
        }

        if ($data['name'] === '') {
            $data['name'] = $this->generateTermName($data['session_number'], $data['year']);
        }

        try {
            $this->pdo->beginTransaction();

            if ($data['is_active']) {
                $this->pdo->exec("UPDATE terms SET is_active = 0, updated_by = {$userId} WHERE is_active = 1 AND is_deleted = 0");
            }

            $stmt = $this->pdo->prepare("INSERT INTO terms (name, session_number, year, start_date, end_date, is_active, description, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['session_number'],
                $data['year'],
                $data['start_date'],
                $data['end_date'],
                $data['is_active'],
                $data['description'] ?: null,
                $userId,
                $userId,
            ]);

            $termId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();

            audit_log('CREATE', 'Term', (string)$termId, null, $data, "Created legislative term: {$data['name']}");
            system_log('INFO', "Term created: {$data['name']} (ID: {$termId})");
            flash_set('success', "Term \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Term create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create term: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/legislative-terms');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid term ID.');
            redirect('master/legislative-terms');
        }

        $stmt = $this->pdo->prepare("SELECT * FROM terms WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $term = $stmt->fetch();

        if (!$term) {
            flash_set('error', 'Term not found.');
            redirect('master/legislative-terms');
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $term]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid term ID.');
            redirect('master/legislative-terms');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'session_number' => (int)($_POST['session_number'] ?? 1),
            'year' => (int)($_POST['year'] ?? date('Y')),
            'start_date' => trim($_POST['start_date'] ?? ''),
            'end_date' => trim($_POST['end_date'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateTerm($data);
        $overlapError = $this->checkDateOverlap($data['start_date'], $data['end_date'], $id);
        if ($overlapError) {
            $errors[] = $overlapError;
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/legislative-terms');
        }

        if ($data['name'] === '') {
            $data['name'] = $this->generateTermName($data['session_number'], $data['year']);
        }

        try {
            $this->pdo->beginTransaction();

            $oldStmt = $this->pdo->prepare("SELECT * FROM terms WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            if ($data['is_active']) {
                $this->pdo->prepare("UPDATE terms SET is_active = 0, updated_by = ? WHERE is_active = 1 AND is_deleted = 0 AND id != ?")->execute([$userId, $id]);
            }

            $stmt = $this->pdo->prepare("UPDATE terms SET name = ?, session_number = ?, year = ?, start_date = ?, end_date = ?, is_active = ?, description = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['session_number'],
                $data['year'],
                $data['start_date'],
                $data['end_date'],
                $data['is_active'],
                $data['description'] ?: null,
                $userId,
                $id,
            ]);

            $this->pdo->commit();

            audit_log('UPDATE', 'Term', (string)$id, $oldRecord ?: null, $data, "Updated legislative term: {$data['name']}");
            system_log('INFO', "Term updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Term \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Term update failed', ['error' => $e->getMessage(), 'term_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update term: ' . $e->getMessage());
        }

        redirect('master/legislative-terms');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid term ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM terms WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $term = $stmt->fetch();

        if (!$term) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Term not found.']);
            exit;
        }

        if ($term['is_active']) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cannot delete the currently active term. Please deactivate it first.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM terms WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE terms SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Term', (string)$id, $oldRecord ?: null, null, "Soft-deleted legislative term: {$term['name']}");
            system_log('INFO', "Term deleted (soft): {$term['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Term \"{$term['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete term (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete term.']);
        }
        exit;
    }

    public function setActive(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid term ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM terms WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $term = $stmt->fetch();

        if (!$term) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Term not found.']);
            exit;
        }

        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("UPDATE terms SET is_active = 0, updated_by = ? WHERE is_active = 1 AND is_deleted = 0 AND id != ?")->execute([$userId, $id]);

            $newActive = 1;
            if ($term['is_active']) {
                $newActive = 0;
            }
            $stmt = $this->pdo->prepare("UPDATE terms SET is_active = ?, updated_by = ? WHERE id = ?");
            $stmt->execute([$newActive, $userId, $id]);

            $oldStatus = $term['is_active'] ? 'Active' : 'Inactive';
            $newStatusLabel = $newActive ? 'Active' : 'Inactive';

            $this->pdo->commit();
            $action = $newActive ? 'activated' : 'deactivated';

            audit_log('UPDATE', 'Term', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Term \"{$term['name']}\" {$action}");
            system_log('INFO', "Term status {$action}: {$term['name']} (ID: {$id})");

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Term \"{$term['name']}\" has been {$action}."]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Term setActive failed', ['error' => $e->getMessage(), 'term_id' => $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
        }
        exit;
    }

    public function clone(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid term ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT * FROM terms WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $term = $stmt->fetch();

        if (!$term) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Term not found.']);
            exit;
        }

        try {
            $this->pdo->beginTransaction();

            $newSession = (int)$term['session_number'] + 1;
            $newYear = (int)$term['year'] + 1;
            $newStart = date('Y-m-d', strtotime($term['start_date'] . ' +1 year'));
            $newEnd = date('Y-m-d', strtotime($term['end_date'] . ' +1 year'));
            $newName = $this->generateTermName($newSession, $newYear);

            $baseName = $newName;
            $counter = 1;
            while (true) {
                $check = $this->pdo->prepare("SELECT id FROM terms WHERE name = ? AND is_deleted = 0 LIMIT 1");
                $check->execute([$newName]);
                if (!$check->fetch()) break;
                $newName = "{$baseName} (Copy {$counter})";
                $counter++;
            }

            $insert = $this->pdo->prepare("INSERT INTO terms (name, session_number, year, start_date, end_date, is_active, description, created_by, updated_by) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)");
            $insert->execute([
                $newName,
                $newSession,
                $newYear,
                $newStart,
                $newEnd,
                $term['description'],
                $userId,
                $userId,
            ]);
            $newTermId = (int)$this->pdo->lastInsertId();

            $legStmt = $this->pdo->prepare("SELECT sp_member_id, date_assigned, notes FROM term_legislators WHERE term_id = ?");
            $legStmt->execute([$id]);
            $legislators = $legStmt->fetchAll();

            if (!empty($legislators)) {
                $assignStmt = $this->pdo->prepare("INSERT INTO term_legislators (term_id, sp_member_id, date_assigned, notes, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($legislators as $leg) {
                    $assignStmt->execute([
                        $newTermId,
                        (int)$leg['sp_member_id'],
                        $leg['date_assigned'],
                        $leg['notes'],
                        $userId,
                        $userId,
                    ]);
                }
            }

            $this->pdo->commit();

            audit_log('CREATE', 'Term', (string)$newTermId, null, ['cloned_from_id' => $id, 'name' => $newName, 'session_number' => $newSession, 'year' => $newYear, 'legislators_count' => count($legislators)], "Cloned term \"{$term['name']}\" -> \"{$newName}\".");
            system_log('INFO', "Term cloned: {$term['name']} (ID: {$id}) -> {$newName} (ID: {$newTermId})");

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Term cloned successfully as \"{$newName}\".", 'new_id' => $newTermId]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Term clone failed', ['error' => $e->getMessage(), 'term_id' => $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to clone term: ' . $e->getMessage()]);
        }
        exit;
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid term ID.');
            redirect('master/legislative-terms');
        }

        $termStmt = $this->pdo->prepare("SELECT t.*,
            (SELECT COUNT(*) FROM term_legislators tl WHERE tl.term_id = t.id) AS member_count
        FROM terms t WHERE t.id = ? AND t.is_deleted = 0 LIMIT 1");
        $termStmt->execute([$id]);
        $term = $termStmt->fetch();

        if (!$term) {
            flash_set('error', 'Term not found.');
            redirect('master/legislative-terms');
        }

        $legStmt = $this->pdo->prepare("SELECT tl.*, 
            sm.first_name, sm.middle_name, sm.last_name, sm.suffix, 
            sm.photo_path, sm.position, sm.sp_member_id,
            d.name as district_name
            FROM term_legislators tl
            INNER JOIN sp_members sm ON sm.sp_member_id = tl.sp_member_id
            LEFT JOIN districts d ON d.id = sm.district_id
            WHERE tl.term_id = ?
            ORDER BY sm.position ASC, sm.last_name ASC");
        $legStmt->execute([$id]);
        $legislators = $legStmt->fetchAll();

        $availStmt = $this->pdo->prepare("SELECT sm.sp_member_id as id, 
            sm.first_name, sm.middle_name, sm.last_name, sm.suffix, 
            sm.position, sm.photo_path,
            d.name as district_name
            FROM sp_members sm
            LEFT JOIN districts d ON d.id = sm.district_id
            WHERE sm.is_deleted = 0 AND sm.is_active = 1
            AND sm.sp_member_id NOT IN (SELECT sp_member_id FROM term_legislators WHERE term_id = ?)
            ORDER BY sm.position ASC, sm.last_name ASC");
        $availStmt->execute([$id]);
        $availableMembers = $availStmt->fetchAll();

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Term Details: ' . htmlspecialchars($term['name']);
        $pageSubtitle = $term['description'] ?? 'Manage SP member assignments for this legislative session';
        $accent = 'primary';

        require __DIR__ . '/../../../resources/views/master/legislative-terms/show.php';
    }

    public function assignLegislators(): void
    {
        $termId = (int)($_POST['term_id'] ?? 0);
        if ($termId <= 0) {
            flash_set('error', 'Invalid term ID.');
            redirect('master/legislative-terms/show?id=' . $termId);
        }

        $memberIds = $_POST['member_ids'] ?? [];

        if (empty($memberIds)) {
            flash_set('error', 'Please select at least one member to assign.');
            redirect('master/legislative-terms/show?id=' . $termId);
        }

        $userId = auth_id();

        try {
            $this->pdo->beginTransaction();
            
            // Validate that all member IDs exist and are active
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $validateStmt = $this->pdo->prepare("SELECT sp_member_id FROM sp_members WHERE sp_member_id IN ({$placeholders}) AND is_deleted = 0 AND is_active = 1");
            $validateStmt->execute($memberIds);
            $validIds = $validateStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validIds) !== count($memberIds)) {
                $this->pdo->rollBack();
                flash_set('error', 'One or more selected members are invalid or inactive.');
                redirect('master/legislative-terms/show?id=' . $termId);
            }
            
            $assignStmt = $this->pdo->prepare("INSERT IGNORE INTO term_legislators (term_id, sp_member_id, date_assigned, created_by, updated_by) VALUES (?, ?, NOW(), ?, ?)");
            $count = 0;
            foreach ($memberIds as $mid) {
                $mid = (int)$mid;
                if ($mid > 0) {
                    $assignStmt->execute([$termId, $mid, $userId, $userId]);
                    if ($assignStmt->rowCount() > 0) $count++;
                }
            }
            $this->pdo->commit();

            audit_log('UPDATE', 'Term', (string)$termId, null, ['assigned_count' => $count, 'sp_member_ids' => $memberIds], "Assigned {$count} SP member(s) to term");
            system_log('INFO', "Assigned {$count} SP members to term {$termId}");
            flash_set('success', "Assigned {$count} member(s) successfully.");
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Term assign legislators failed', ['error' => $e->getMessage(), 'term_id' => $termId]);
            flash_set('error', 'Failed to assign members: ' . $e->getMessage());
        }

        redirect('master/legislative-terms/show?id=' . $termId);
    }

    public function removeLegislator(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $termId = (int)($_POST['term_id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid assignment ID.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT tl.*, sm.first_name, sm.last_name, sm.position 
            FROM term_legislators tl 
            LEFT JOIN sp_members sm ON sm.sp_member_id = tl.sp_member_id 
            WHERE tl.id = ? LIMIT 1");
        $oldStmt->execute([$id]);
        $assignment = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("DELETE FROM term_legislators WHERE id = ?");
        $result = $stmt->execute([$id]);

        header('Content-Type: application/json');
        if ($result) {
            $memberName = trim(($assignment['first_name'] ?? '') . ' ' . ($assignment['last_name'] ?? '')) ?: 'Member';
            $memberPosition = $assignment['position'] ?? 'SP Member';
            $tid = $assignment['term_id'] ?? $termId;
            audit_log('UPDATE', 'TermLegislator', (string)$id, $assignment ?: null, null, "Removed SP member {$memberName} ({$memberPosition}) from term assignment");
            if ($tid) {
                audit_log('UPDATE', 'Term', (string)$tid, null, ['removed_member' => $memberName, 'position' => $memberPosition], "Removed SP member {$memberName} from term");
            }
            system_log('INFO', "Removed SP member from term_legislators assignment ID: {$id}");
            echo json_encode(['success' => true, 'message' => 'Member removed successfully.']);
        } else {
            system_log('WARNING', "Failed to remove legislator assignment (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to remove member.']);
        }
        exit;
    }

    public function exportCsv(): void
    {
        $stmt = $this->pdo->query("SELECT t.id, t.name, t.session_number, t.year, t.start_date, t.end_date,
            CASE t.is_active WHEN 1 THEN 'Active' ELSE 'Inactive' END AS status,
            (SELECT COUNT(*) FROM term_legislators tl WHERE tl.term_id = t.id) AS total_members,
            t.description, t.created_at
        FROM terms t WHERE t.is_deleted = 0 ORDER BY t.start_date DESC");
        $terms = $stmt->fetchAll();

        $filename = 'legislative_terms_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Session #', 'Year', 'Start Date', 'End Date', 'Status', 'Total Members', 'Description', 'Created At']);

        foreach ($terms as $t) {
            fputcsv($output, [
                $t['id'], $t['name'], $t['session_number'], $t['year'],
                $t['start_date'], $t['end_date'], $t['status'],
                $t['total_members'],
                $t['description'] ?? '', $t['created_at'],
            ]);
        }
        fclose($output);

        audit_log('EXPORT', 'Term', null, null, ['exported_count' => count($terms)], "Exported " . count($terms) . " legislative terms to CSV");
        system_log('INFO', "Legislative terms CSV exported (" . count($terms) . " records)");
        exit;
    }

    public function generateNameSuggestion(): void
    {
        $sessionNumber = (int)($_GET['session_number'] ?? 1);
        $year = (int)($_GET['year'] ?? date('Y'));

        $name = $this->generateTermName($sessionNumber, $year);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'name' => $name]);
        exit;
    }

    protected function validateTerm(array $data): array
    {
        $errors = [];

        if ($data['session_number'] <= 0) {
            $errors[] = 'Session number must be a positive integer.';
        }
        if ($data['year'] < 1900 || $data['year'] > 2999) {
            $errors[] = 'Year must be a valid 4-digit year.';
        }
        if ($data['start_date'] === '' || !strtotime($data['start_date'])) {
            $errors[] = 'Start date is required and must be valid.';
        }
        if ($data['end_date'] === '' || !strtotime($data['end_date'])) {
            $errors[] = 'End date is required and must be valid.';
        }
        if ($data['start_date'] !== '' && $data['end_date'] !== '') {
            if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
                $errors[] = 'End date must be after the start date.';
            }
        }

        return $errors;
    }

    protected function checkDateOverlap(string $start, string $end, ?int $excludeId = null): ?string
    {
        if ($start === '' || $end === '') return null;
        if (!strtotime($start) || !strtotime($end)) return null;

        $params = [$start, $end, $start, $end];
        $excludeSql = '';
        if ($excludeId !== null) {
            $excludeSql = 'AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare("SELECT id, name, start_date, end_date FROM terms
            WHERE is_deleted = 0
            AND ((start_date <= ? AND end_date >= ?)
                 OR (start_date <= ? AND end_date >= ?)
                 OR (start_date >= ? AND end_date <= ?))
            {$excludeSql}
            LIMIT 1");
        $params[] = $start;
        $params[] = $end;
        $stmt->execute($params);
        $overlap = $stmt->fetch();

        if ($overlap) {
            return "Date range overlaps with existing term: \"{$overlap['name']}\" ({$overlap['start_date']} to {$overlap['end_date']}).";
        }
        return null;
    }

    protected function generateTermName(int $sessionNumber, int $year): string
    {
        $suffixes = ['th', 'st', 'nd', 'rd'];
        $mod100 = $sessionNumber % 100;
        $mod10 = $sessionNumber % 10;
        $suffix = ($mod100 >= 11 && $mod100 <= 13) ? 'th' : ($suffixes[$mod10] ?? 'th');
        return "{$sessionNumber}{$suffix} Congress - {$year} Regular Session";
    }
}
