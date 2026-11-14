<?php
ob_start();
$h = static fn(mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$selected = static fn(string $field, mixed $value): string => (string)($formData[$field] ?? '') === (string)$value ? 'selected' : '';
$renderRecords = static function (string $title, array $records) use ($h): void {
    ?>
    <details class="rounded-xl border border-gray-200 bg-white">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
            <span class="font-semibold text-gray-900"><?= $h($title) ?></span>
            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600"><?= count($records) ?></span>
        </summary>
        <?php if ($records === []): ?>
            <p class="border-t border-gray-100 px-5 py-4 text-sm text-gray-500">No related records.</p>
        <?php else: ?>
            <div class="space-y-3 border-t border-gray-100 p-4">
                <?php foreach ($records as $record): ?>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <?php if (isset($record['id'])): ?>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Record #<?= (int)$record['id'] ?></p>
                        <?php endif; ?>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <?php foreach ($record as $field => $value): ?>
                                <?php
                                if ($field === 'id' || str_ends_with($field, '_id')) {
                                    continue;
                                }
                                if ($field === 'metadata' && is_string($value)) {
                                    $decoded = json_decode($value, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $value = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    }
                                }
                                if (is_array($value) || is_object($value)) {
                                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                }
                                ?>
                                <div class="<?= in_array($field, ['remarks', 'notes', 'description', 'summary_of_findings', 'metadata', 'additional_details', 'deferred_remarks', 'withdrawal_remark'], true) ? 'sm:col-span-2' : '' ?>">
                                    <dt class="text-xs font-medium text-gray-500"><?= $h(ucwords(str_replace('_', ' ', $field))) ?></dt>
                                    <dd class="mt-1 break-words whitespace-pre-wrap text-sm text-gray-800"><?= $h($value === null || $value === '' ? '—' : $value) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </details>
    <?php
};
$renderRelatedEditors = static function (string $title, array $tables) use (
    $h,
    $relatedEditableRecords,
    $relatedFieldSchemas,
    $relatedSelectOptions,
    $document
): void {
    $tableLabels = [
        'plenary_document_details' => 'Plenary processing details',
        'plenary_readings' => 'Plenary readings',
        'plenary_second_reading_details' => 'Second Reading approval details',
        'document_authors' => 'Document authors and co-authors',
        'document_committees' => 'Assigned committees',
        'comm_committee_assignments' => 'Communication committee assignments',
        'plenary_comm_first_reading' => 'Plenary Communication first reading',
        'plenary_cases_first_reading' => 'Plenary Cases first reading',
        'plenary_comm_adoptions' => 'Plenary Communication adoption',
        'plenary_cases_adoptions' => 'Plenary Cases adoption',
        'plenary_committee_report_adoptions' => 'Committee report adoption decision',
        'committee_cycles' => 'Committee cycles',
        'committee_cycle_committees' => 'Committees assigned to cycles',
        'committee_cycle_endorsements' => 'Opinion office endorsements',
        'committee_endorsement_submissions' => 'Opinion and compliance submission details',
        'committee_opinion_resolutions' => 'Committee opinion resolutions',
        'agendas' => 'Committee agendas',
        'agenda_committees' => 'Committees assigned to agendas',
        'agenda_chairpersons' => 'Agenda chairpersons',
        'committee_hearings' => 'Committee hearings',
        'committee_reports' => 'Committee reports',
        'committee_report_committees' => 'Committees assigned to reports',
        'committee_report_attachments' => 'Committee report attachment names',
        'committee_report_attachment_transmittals' => 'Committee report attachment transmittals',
        'document_attachments' => 'Document attachment names',
        'committee_cases' => 'Committee case details',
        'committee_case_actions' => 'Committee case actions',
        'committee_communications' => 'Committee communication details',
    ];
    ?>
    <details class="rounded-xl border border-amber-200 bg-amber-50">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-amber-950">
            <?= $h($title) ?> · correct existing records
        </summary>
        <div class="space-y-5 border-t border-amber-200 p-4">
            <p class="text-sm text-amber-900">Edit the saved Plenary or Committee values below. Each record saves separately and is added to the document correction history. Document-level workflow status and public visibility remain protected; file contents cannot be changed here.</p>
            <?php foreach ($tables as $table): ?>
                <?php $records = $relatedEditableRecords[$table] ?? []; ?>
                <?php if ($records === []) continue; ?>
                <section class="rounded-lg border border-amber-100 bg-white p-4">
                    <h3 class="font-semibold text-gray-900"><?= $h($tableLabels[$table] ?? ucwords(str_replace('_', ' ', $table))) ?></h3>
                    <div class="mt-3 space-y-4">
                        <?php foreach ($records as $record): ?>
                                <?php
                                $schema = $relatedFieldSchemas[$table] ?? [];
                                $editableFields = array_intersect_key($schema, array_flip(array_keys($record)));
                                ?>
                                <form method="POST" action="<?= BASE_URL ?>/master/documents/update-related" class="rounded-lg border border-gray-200 p-4">
                                    <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
                                    <input type="hidden" name="table" value="<?= $h($table) ?>">
                                    <input type="hidden" name="record_id" value="<?= (int)$record['id'] ?>">
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Record #<?= (int)$record['id'] ?></p>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <?php foreach ($editableFields as $field => $column): ?>
                                            <?php
                                            $type = strtolower((string)$column['Type']);
                                            $value = $record[$field] ?? '';
                                            if ($value !== '' && $value !== null) {
                                                if ($type === 'date') {
                                                    $value = substr((string)$value, 0, 10);
                                                } elseif ($type === 'time') {
                                                    $value = substr((string)$value, 0, 5);
                                                } elseif (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                                                    $value = str_replace(' ', 'T', substr((string)$value, 0, 16));
                                                }
                                            }
                                            $label = ucwords(str_replace('_', ' ', $field));
                                            $options = $relatedSelectOptions[$field] ?? null;
                                            $enumOptions = [];
                                            if (preg_match('/^enum\((.*)\)$/i', (string)$column['Type'], $enumMatch)) {
                                                preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $enumMatch[1], $enumMatches);
                                                $enumOptions = $enumMatches[1] ?? [];
                                            }
                                            $isBoolean = preg_match('/^(tinyint|bit)\(1\)/', $type) === 1;
                                            $isText = str_contains($type, 'text') || in_array($field, ['remarks', 'notes', 'description', 'summary_of_findings', 'approved_subject_matter', 'additional_details', 'deferred_remarks', 'withdrawal_remark', 'nature_of_case', 'complainant_details', 'respondents', 'final_outcome_remarks', 'sender_details'], true);
                                            $isDateTime = str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp');
                                            $inputType = $type === 'date' ? 'date' : ($type === 'time' ? 'time' : ($isDateTime ? 'datetime-local' : (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $type) ? 'number' : 'text')));
                                            ?>
                                            <label class="block text-sm font-medium text-gray-700 <?= $isText ? 'md:col-span-2' : '' ?>">
                                                <?= $h($label) ?>
                                                <?php if (is_array($options) || $enumOptions !== [] || $isBoolean): ?>
                                                    <select name="fields[<?= $h($field) ?>]" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                                                        <?php if (($column['Null'] ?? 'NO') === 'YES'): ?>
                                                            <option value="">Not specified</option>
                                                        <?php endif; ?>
                                                        <?php if (is_array($options)): ?>
                                                            <?php foreach ($options as $option): ?>
                                                                <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>><?= $h($option['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        <?php elseif ($isBoolean): ?>
                                                            <option value="0" <?= (string)$value === '0' ? 'selected' : '' ?>>No</option>
                                                            <option value="1" <?= (string)$value === '1' ? 'selected' : '' ?>>Yes</option>
                                                        <?php else: ?>
                                                            <?php foreach ($enumOptions as $option): ?>
                                                                <option value="<?= $h($option) ?>" <?= (string)$value === $option ? 'selected' : '' ?>><?= $h(ucwords(strtolower(str_replace('_', ' ', $option)))) ?></option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                <?php elseif ($isText): ?>
                                                    <textarea name="fields[<?= $h($field) ?>]" rows="3" class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?= $h($value) ?></textarea>
                                                <?php else: ?>
                                                    <input type="<?= $h($inputType) ?>" name="fields[<?= $h($field) ?>]" value="<?= $h($value) ?>"<?= $inputType === 'number' ? ' step="any"' : '' ?> class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save this record</button>
                                    </div>
                                </form>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
};
$renderLegislativeEditors = static function (string $title, array $tables) use (
    $h,
    $relatedEditableRecords,
    $relatedFieldSchemas,
    $relatedSelectOptions,
    $document
): void {
    $tableLabels = [
        'plenary_document_details' => 'Bill / ordinance details',
        'plenary_second_reading_details' => 'Second Reading approval',
        'document_authors' => 'Author and co-author',
        'plenary_comm_first_reading' => 'Communication first reading',
        'plenary_comm_adoptions' => 'Adopted communication',
        'plenary_cases_adoptions' => 'Adopted case',
        'committee_cycle_endorsements' => 'Opinion office',
        'committee_endorsement_submissions' => 'Opinion / compliance',
        'agendas' => 'Committee agenda',
        'agenda_committees' => 'Agenda committee',
        'committee_reports' => 'Committee report',
        'committee_report_committees' => 'Report committee',
        'committee_cases' => 'Case details',
    ];
    $fieldLabels = [
        'proposed_number' => 'Proposed number',
        'target_first_reading_date' => 'Target first reading date',
        'session_type' => 'Session type',
        'additional_details' => 'Additional legislative details',
        'approved_no' => 'Approved number',
        'date_finalized' => 'Approval / finalization date',
        'approved_subject_matter' => 'Approved subject matter',
        'sp_member_id' => 'SP Member',
        'author_role' => 'Author role',
        'resolution_number' => 'Resolution number',
        'final_output_type' => 'Final output type',
        'adopted_at' => 'Adopted at',
        'opinion_office_id' => 'Opinion / compliance office',
        'remarks' => 'Remarks',
        'opinion_type' => 'Opinion',
        'opinion_file_attachment_id' => 'Opinion file',
        'compliance_file_attachment_id' => 'Compliance file',
        'agenda_number' => 'Agenda number',
        'agenda_type' => 'Agenda type',
        'agenda_date' => 'Agenda date',
        'agenda_time' => 'Agenda time',
        'venue' => 'Venue',
        'committee_id' => 'Committee',
        'report_type' => 'Report type',
        'report_number' => 'Report number',
        'summary_of_findings' => 'Summary of findings',
        'comm_resolution_number' => 'Resolution number',
        'comm_proposed_number' => 'Proposed number',
        'comm_adopted_at' => 'Adopted at',
        'docket_number' => 'Docket number',
        'nature_of_case' => 'Nature of case',
        'complainant_details' => 'Complainant details',
        'complainant_municipality' => 'Complainant municipality / city',
        'respondents' => 'Respondents',
    ];
    $count = array_sum(array_map(
        static fn(string $table): int => count($relatedEditableRecords[$table] ?? []),
        $tables
    ));
    if ($count === 0) {
        return;
    }
    ?>
    <details class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
            <span>
                <span class="block font-semibold text-gray-900"><?= $h($title) ?></span>
                <span class="mt-1 block text-sm font-normal text-gray-500">Correct only key legislative information. Workflow decisions and public visibility are protected.</span>
            </span>
            <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600"><?= $count ?> <?= $count === 1 ? 'record' : 'records' ?></span>
        </summary>
        <div class="space-y-4 border-t border-gray-100 p-4 sm:p-5">
            <?php foreach ($tables as $table): ?>
                <?php foreach (($relatedEditableRecords[$table] ?? []) as $record): ?>
                    <?php
                    $summary = match ($table) {
                        'plenary_document_details' => 'Proposed No. ' . ($record['proposed_number'] ?: 'not set'),
                        'plenary_second_reading_details' => 'Approved No. ' . ($record['approved_no'] ?: 'not set'),
                        'document_authors' => ($record['member_name'] ?: 'Member not set'),
                        'plenary_comm_first_reading', 'plenary_comm_adoptions', 'plenary_cases_adoptions' =>
                            ($record['resolution_number'] ?: $record['proposed_number'] ?: 'Legislative number not set'),
                        'committee_cycle_endorsements' => ($record['office_name'] ?? 'Office not set')
                            . ' · Opinion ' . (int)($record['endorsement_number'] ?? 0),
                        'committee_endorsement_submissions' => 'Opinion / compliance record #' . (int)$record['id'],
                        'agendas' => 'Agenda ' . ($record['agenda_number'] ?: 'number not set')
                            . (!empty($record['agenda_date']) ? ' · ' . $record['agenda_date'] : ''),
                        'agenda_committees', 'committee_report_committees' =>
                            ($record['committee_name'] ?? 'Committee record #' . (int)$record['id']),
                        'committee_reports' => ($record['report_number'] ?: $record['report_type']),
                        'committee_cases' => 'Docket ' . ($record['docket_number'] ?: 'number not set'),
                        default => 'Record #' . (int)$record['id'],
                    };
                    $fields = $relatedFieldSchemas[$table] ?? [];
                    ?>
                    <details class="rounded-xl border border-gray-200">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <span>
                                <span class="block text-sm font-semibold text-gray-800"><?= $h($tableLabels[$table] ?? 'Legislative record') ?></span>
                                <span class="mt-0.5 block text-xs text-gray-500"><?= $h($summary) ?></span>
                            </span>
                            <span class="text-sm font-medium text-primary">Edit record</span>
                        </summary>
                        <form method="POST" action="<?= BASE_URL ?>/master/documents/update-related" class="border-t border-gray-100 p-4">
                            <input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
                            <input type="hidden" name="table" value="<?= $h($table) ?>">
                            <input type="hidden" name="record_id" value="<?= (int)$record['id'] ?>">
                            <div class="grid gap-4 md:grid-cols-2">
                                <?php foreach ($fields as $field => $column): ?>
                                    <?php
                                    $value = $record[$field] ?? '';
                                    $type = strtolower((string)$column['Type']);
                                    if ($value !== '' && $value !== null) {
                                        if ($type === 'date') {
                                            $value = substr((string)$value, 0, 10);
                                        } elseif ($type === 'time') {
                                            $value = substr((string)$value, 0, 5);
                                        } elseif (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                                            $value = str_replace(' ', 'T', substr((string)$value, 0, 16));
                                        }
                                    }
                                    $options = $relatedSelectOptions[$field] ?? null;
                                    $enumOptions = [];
                                    if (preg_match('/^enum\((.*)\)$/i', (string)$column['Type'], $enumMatch)) {
                                        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $enumMatch[1], $enumMatches);
                                        $enumOptions = $enumMatches[1] ?? [];
                                    }
                                    $isText = str_contains($type, 'text') || in_array($field, [
                                        'additional_details', 'approved_subject_matter', 'summary_of_findings',
                                        'complainant_details', 'respondents',
                                    ], true);
                                    $isDateTime = str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp');
                                    $inputType = $type === 'date' ? 'date'
                                        : ($type === 'time' ? 'time'
                                            : ($isDateTime ? 'datetime-local'
                                                : (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)
                                                    ? 'number' : 'text')));
                                    ?>
                                    <label class="block text-sm font-medium text-gray-700 <?= $isText ? 'md:col-span-2' : '' ?>">
                                        <?= $h($fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field))) ?>
                                        <?php if (is_array($options) || $enumOptions !== []): ?>
                                            <select name="fields[<?= $h($field) ?>]" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                                <?php if (($column['Null'] ?? 'NO') === 'YES'): ?>
                                                    <option value="">Not specified</option>
                                                <?php endif; ?>
                                                <?php if (is_array($options)): ?>
                                                    <?php foreach ($options as $option): ?>
                                                        <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>><?= $h($option['name']) ?></option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php foreach ($enumOptions as $option): ?>
                                                        <option value="<?= $h($option) ?>" <?= (string)$value === $option ? 'selected' : '' ?>><?= $h(ucwords(strtolower(str_replace('_', ' ', $option)))) ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        <?php elseif ($isText): ?>
                                            <textarea name="fields[<?= $h($field) ?>]" rows="3" class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= $h($value) ?></textarea>
                                        <?php else: ?>
                                            <input type="<?= $h($inputType) ?>" name="fields[<?= $h($field) ?>]" value="<?= $h($value) ?>" class="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-4">
                                <span class="text-xs text-gray-500">This saves only the fields above and records the correction.</span>
                                <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Save correction</button>
                            </div>
                        </form>
                    </details>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
};
?>
<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="flex flex-col gap-4 px-6 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div>
                <p class="text-sm font-medium text-blue-100">SUPER ADMIN · <?= $h($document['tracking_number']) ?></p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="mt-2 text-sm leading-6 text-blue-100"><?= htmlspecialchars($pageSubtitle) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/master/documents" class="inline-flex justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary hover:bg-blue-50">Back to registry</a>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/master/documents/update" class="space-y-6">
        <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-900">Document details</h2>
                <p class="mt-1 text-sm text-gray-500">Correct the document classification, receipt details, and source information. Tracking number and workflow state are protected.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block text-sm font-medium text-gray-700">
                    Tracking number <span class="ml-1 text-xs font-normal text-gray-400">system generated</span>
                    <input type="text" value="<?= $h($document['tracking_number']) ?>" readonly class="mt-1.5 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500">
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Document type <span class="text-red-500">*</span>
                    <select name="document_type_id" required class="mt-1.5 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Select document type</option>
                        <?php foreach ($documentTypes as $type): ?>
                            <option value="<?= (int)$type['id'] ?>" <?= $selected('document_type_id', $type['id']) ?>>
                                <?= $h($type['name']) ?><?= !(int)$type['is_active'] ? ' (inactive)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700 md:col-span-2">
                    Subject matter <span class="text-red-500">*</span>
                    <textarea name="subject_matter" rows="3" required class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= $h($formData['subject_matter']) ?></textarea>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Date received <span class="text-red-500">*</span>
                    <input type="date" name="date_received" required value="<?= $h($formData['date_received']) ?>" class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Time received <span class="text-red-500">*</span>
                    <input type="time" name="time_received" required value="<?= $h(substr((string)$formData['time_received'], 0, 5)) ?>" class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-900">Source information</h2>
                <p class="mt-1 text-sm text-gray-500">Update the submitting source and contact details if they were recorded incorrectly.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block text-sm font-medium text-gray-700 md:col-span-2">
                    Source type <span class="text-red-500">*</span>
                    <select name="source_type_id" id="source_type_id" required class="mt-1.5 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Select source type</option>
                        <?php foreach ($sourceTypes as $type): ?>
                            <option value="<?= (int)$type['id'] ?>" data-source-name="<?= $h($type['name']) ?>" <?= $selected('source_type_id', $type['id']) ?>><?= $h($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label id="externalOfficeFields" class="hidden block text-sm font-medium text-gray-700">
                    Provincial government office <span class="text-red-500">*</span>
                    <select name="external_office_id" id="external_office_id" disabled class="mt-1.5 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Not specified</option>
                        <?php foreach ($offices as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= $selected('external_office_id', $option['id']) ?>><?= $h($option['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label id="hospitalFields" class="hidden block text-sm font-medium text-gray-700">
                    Hospital <span class="text-red-500">*</span>
                    <select name="hospital_id" id="hospital_id" disabled class="mt-1.5 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Not specified</option>
                        <?php foreach ($hospitals as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= $selected('hospital_id', $option['id']) ?>><?= $h($option['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label id="spMemberFields" class="hidden block text-sm font-medium text-gray-700">
                    SP Member <span class="text-red-500">*</span>
                    <select name="sp_member_id" id="sp_member_id" disabled class="mt-1.5 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Select an SP Member</option>
                        <?php foreach ($spMembers as $member):
                            $fullName = trim(implode(' ', array_filter([
                                $member['first_name'] ?? '',
                                $member['middle_name'] ?? '',
                                $member['last_name'] ?? '',
                                $member['suffix'] ?? '',
                            ])));
                            $memberSelected = (string)($formData['sp_member_id'] ?? '') === (string)$member['sp_member_id']
                                || ((string)($formData['sp_member_id'] ?? '') === ''
                                    && $fullName === (string)($formData['source_name'] ?? ''));
                        ?>
                            <option value="<?= (int)$member['sp_member_id'] ?>" <?= $memberSelected ? 'selected' : '' ?>><?= $h($fullName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label id="agencyNameField" class="hidden block text-sm font-medium text-gray-700">
                    Agency name <span class="text-red-500">*</span>
                    <input type="text" name="source_name" id="source_name_agency" maxlength="255" value="<?= $h($formData['source_name']) ?>" disabled class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>

                <label id="clientNameField" class="hidden block text-sm font-medium text-gray-700">
                    Client name <span class="text-red-500">*</span>
                    <input type="text" name="source_name" id="source_name_client" maxlength="255" value="<?= $h($formData['source_name']) ?>" disabled class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>

                <label id="municipalityField" class="hidden block text-sm font-medium text-gray-700">
                    Municipality / city <span class="text-red-500">*</span>
                    <select name="municipality_id" id="municipality_id" disabled class="mt-1.5 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Not specified</option>
                        <?php foreach ($municipalities as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= $selected('municipality_id', $option['id']) ?>><?= $h($option['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div id="contactFields" class="hidden gap-5 md:col-span-2 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Contact number
                        <input type="text" id="source_contact_number" name="source_contact_number" maxlength="50" value="<?= $h($formData['source_contact_number']) ?>" class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </label>
                    <label class="block text-sm font-medium text-gray-700">
                        Liaison name
                        <input type="text" id="source_liaison_name" name="source_liaison_name" maxlength="255" value="<?= $h($formData['source_liaison_name']) ?>" class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 md:col-span-2">
                        Source address
                        <textarea name="source_address" id="source_address" rows="2" class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= $h($formData['source_address']) ?></textarea>
                    </label>
                </div>
                <label class="block text-sm font-medium text-gray-700 md:col-span-2">
                    Remarks
                    <textarea name="remarks" rows="3" class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= $h($formData['remarks']) ?></textarea>
                </label>
            </div>
            <div class="mt-6 flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="<?= BASE_URL ?>/master/documents" class="rounded-xl border border-gray-200 px-5 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Save corrections</button>
            </div>
        </section>
    </form>

    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Legislative details</h2>
            <p class="mt-1 text-sm text-gray-500">Open a record only when it needs correction. Each save affects the selected fields only.</p>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <?php $renderLegislativeEditors('Plenary', [
                'plenary_document_details',
                'plenary_second_reading_details',
                'document_authors',
                'plenary_comm_first_reading',
                'plenary_comm_adoptions',
                'plenary_cases_adoptions',
            ]); ?>
            <?php $renderLegislativeEditors('Committee', [
                'committee_cycle_endorsements',
                'committee_endorsement_submissions',
                'agendas',
                'agenda_committees',
                'committee_reports',
                'committee_report_committees',
                'committee_cases',
            ]); ?>
        </div>
        <?php if ($relatedEditableRecords['plenary_document_details'] === [] &&
            $relatedEditableRecords['plenary_second_reading_details'] === [] &&
            $relatedEditableRecords['document_authors'] === [] &&
            $relatedEditableRecords['plenary_comm_first_reading'] === [] &&
            $relatedEditableRecords['plenary_comm_adoptions'] === [] &&
            $relatedEditableRecords['plenary_cases_adoptions'] === [] &&
            $relatedEditableRecords['committee_cycle_endorsements'] === [] &&
            $relatedEditableRecords['committee_endorsement_submissions'] === [] &&
            $relatedEditableRecords['agendas'] === [] &&
            $relatedEditableRecords['agenda_committees'] === [] &&
            $relatedEditableRecords['committee_reports'] === [] &&
            $relatedEditableRecords['committee_report_committees'] === [] &&
            $relatedEditableRecords['committee_cases'] === []): ?>
            <p class="rounded-xl border border-gray-200 bg-white px-5 py-4 text-sm text-gray-500">No Plenary or Committee legislative records are linked to this document yet.</p>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm">
            <span class="font-medium text-gray-700">Workflow tracking</span>
            <span class="text-gray-500"><?= $h($document['status_name'] ?? '—') ?> · <?= $h(str_replace('_', ' ', $document['current_phase'])) ?></span>
        </div>
    </section>

    <details class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-gray-900">
            Correction history <span class="ml-1 text-sm font-normal text-gray-500">(<?= count($auditHistory) ?>)</span>
        </summary>
        <div class="divide-y divide-gray-100 border-t border-gray-100">
            <?php if ($auditHistory === []): ?>
                <p class="px-5 py-4 text-sm text-gray-500">No corrections have been recorded for this document.</p>
            <?php else: ?>
                <?php foreach ($auditHistory as $entry): ?>
                    <details class="px-5 py-3">
                        <summary class="cursor-pointer list-none">
                            <span class="block text-sm font-medium text-gray-800"><?= $h($entry['description'] ?? 'Document updated') ?></span>
                            <span class="mt-1 block text-xs text-gray-500"><?= $h(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?: 'User #' . ($entry['user_id'] ?? '—')) ?> · <?= $h($entry['created_at']) ?></span>
                        </summary>
                        <div class="mt-3 grid gap-3 text-xs md:grid-cols-2">
                            <div class="rounded-lg bg-red-50 p-3">
                                <p class="font-semibold text-red-800">Before</p>
                                <pre class="mt-1 whitespace-pre-wrap break-words text-red-900"><?= $h(json_encode(json_decode($entry['old_values'] ?? '', true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—') ?></pre>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-3">
                                <p class="font-semibold text-emerald-800">After</p>
                                <pre class="mt-1 whitespace-pre-wrap break-words text-emerald-900"><?= $h(json_encode(json_decode($entry['new_values'] ?? '', true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—') ?></pre>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <?php if (false): ?>
    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Plenary details</h2>
            <p class="mt-1 text-sm text-gray-500">Plenary processing, proposed and approved numbers, readings, authors, decisions, and approvals linked to this document.</p>
        </div>
        <?php
        $renderRecords('Plenary processing details', $plenaryDetails);
        $renderRecords('Authors and co-authors', $documentAuthors);
        $renderRecords('Plenary readings and Second Reading outcomes', $plenaryReadings);
        $renderRecords('Plenary Communication first reading', $plenaryCommunicationFirstReadings);
        $renderRecords('Plenary Cases first reading', $plenaryCaseFirstReadings);
        $renderRecords('Communication first reading', $communicationFirstReadings);
        $renderRecords('Communication committee assignments', $communicationCommitteeAssignments);
        $renderRecords('Adopted Communication committee reports', $plenaryCommunicationAdoptions);
        $renderRecords('Adopted Cases committee reports', $plenaryCaseAdoptions);
        $renderRecords('Committee report adoption decisions', $committeeReportAdoptions);
        $renderRecords('Motion for Reconsideration reference', $motionReconsiderationLinks);
        $renderRecords('Document approvals', $documentApprovals);
        $renderRecords('Second Reading approval files', $secondReadingApprovalFiles);
        $renderRecords('Cases committee report adoption files', $plenaryCaseAdoptionFiles);
        $renderRecords('Plenary Communication resolution files', $plenaryCommunicationFiles);
        $renderRecords('Communication resolution files', $communicationFiles);
        ?>
        <?php $renderRelatedEditors('Edit Plenary details', [
            'document_authors',
            'plenary_document_details',
            'plenary_readings',
            'plenary_second_reading_details',
            'plenary_comm_first_reading',
            'plenary_cases_first_reading',
            'plenary_comm_adoptions',
            'plenary_cases_adoptions',
            'plenary_committee_report_adoptions',
        ]); ?>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Committee details</h2>
            <p class="mt-1 text-sm text-gray-500">Committee assignments and cycles, opinion offices and compliance, agendas, hearings, reports, and case or communication records.</p>
        </div>
        <?php
        $renderRecords('Assigned committees', $assignedCommittees);
        $renderRecords('Committee cycles and assigned committees', $committeeCycles);
        $renderRecords('Opinion offices, opinions, and compliance files', $committeeEndorsements);
        $renderRecords('Committee opinion resolutions', $opinionResolutions);
        $renderRecords('Agendas, committees, and chairpersons', $agendas);
        $renderRecords('Committee agenda committee links', $agendaCommittees);
        $renderRecords('Committee agenda chairpersons', $agendaChairpersons);
        $renderRecords('Committee hearings and outcomes', $committeeHearings);
        $renderRecords('Committee reports', $committeeReports);
        $renderRecords('Committee report committees', $committeeReportCommittees);
        $renderRecords('Committee report attachments and return details', $committeeReportAttachments);
        $renderRecords('Committee case details', $committeeCases);
        $renderRecords('Committee case actions and attachments', $committeeCaseActions);
        $renderRecords('Committee case action attachments', $committeeCaseActionAttachments);
        $renderRecords('Committee communication details', $committeeCommunications);
        ?>
        <?php $renderRelatedEditors('Edit Committee details', [
            'document_committees',
            'comm_committee_assignments',
            'committee_cycles',
            'committee_cycle_committees',
            'committee_cycle_endorsements',
            'committee_endorsement_submissions',
            'committee_opinion_resolutions',
            'agendas',
            'agenda_committees',
            'agenda_chairpersons',
            'committee_hearings',
            'committee_reports',
            'committee_report_committees',
            'committee_report_attachments',
            'committee_report_attachment_transmittals',
            'committee_cases',
            'committee_case_actions',
            'committee_communications',
            'document_attachments',
        ]); ?>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Document history and filing</h2>
            <p class="mt-1 text-sm text-gray-500">Routing, assignments, workflow events, checklist completion, and filing details.</p>
        </div>
        <?php
        $renderRecords('Routing history', $documentRoutes);
        $renderRecords('Assignment history', $documentAssignments);
        $renderRecords('Workflow event history', $documentEvents);
        $renderRecords('Checklist requirements', $documentChecklists);
        $renderRecords('Complete document record details', [$documentRecordDetails]);
        $renderRecords('All document attachments', $attachmentDetails);
        $renderRecords('Filing records', $documentFilings);
        $renderRecords('Filing attachments', $filingAttachments);
        ?>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="font-semibold text-gray-900">Workflow and attachments</h2>
                <p class="mt-1 text-xs text-gray-500">Workflow fields are shown for tracking and are not changed by this correction form.</p>
            </div>
            <div class="space-y-3 px-6 py-5 text-sm">
                <div class="flex justify-between gap-4"><span class="text-gray-500">Current status</span><span class="text-right font-medium text-gray-800"><?= $h($document['status_name'] ?? '—') ?></span></div>
                <div class="flex justify-between gap-4"><span class="text-gray-500">Current phase</span><span class="text-right font-medium text-gray-800"><?= $h(str_replace('_', ' ', $document['current_phase'])) ?></span></div>
                <div class="border-t border-gray-100 pt-3">
                    <p class="mb-2 font-medium text-gray-700">Attachments (<?= count($attachments) ?>)</p>
                    <?php if ($attachments === []): ?>
                        <p class="text-gray-500">No attachments are linked to this document.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($attachments as $attachment): ?>
                                <li class="flex flex-wrap justify-between gap-x-4 gap-y-1 rounded-lg bg-gray-50 px-3 py-2">
                                    <span class="break-all text-gray-800"><?= $h($attachment['file_name']) ?></span>
                                    <span class="text-xs text-gray-500"><?= $h($attachment['attachment_type']) ?> · <?= $h(str_replace('_', ' ', $attachment['phase'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="font-semibold text-gray-900">Correction history</h2>
                <p class="mt-1 text-xs text-gray-500">Latest audit entries for this document.</p>
            </div>
            <div class="divide-y divide-gray-100">
                <?php if ($auditHistory === []): ?>
                    <p class="px-6 py-8 text-sm text-gray-500">No corrections have been recorded for this document.</p>
                <?php else: ?>
                    <?php foreach ($auditHistory as $entry): ?>
                        <?php
                        $before = json_decode($entry['old_values'] ?? '', true);
                        $after = json_decode($entry['new_values'] ?? '', true);
                        ?>
                        <details class="px-6 py-4">
                            <summary class="cursor-pointer list-none">
                                <span class="block text-sm font-medium text-gray-800"><?= $h($entry['description'] ?? 'Document updated') ?></span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    <?= $h(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?: 'User #' . ($entry['user_id'] ?? '—')) ?>
                                    · <?= $h($entry['created_at']) ?>
                                </span>
                            </summary>
                            <?php if (is_array($before) && is_array($after)): ?>
                                <div class="mt-3 space-y-2 text-xs">
                                    <?php foreach ($after as $field => $value): ?>
                                        <?php if (($before[$field] ?? null) !== $value): ?>
                                            <div class="rounded-lg bg-gray-50 p-3">
                                                <p class="font-semibold text-gray-700"><?= $h(ucwords(str_replace('_', ' ', $field))) ?></p>
                                                <p class="mt-1 text-gray-500">Before: <?= $h(is_scalar($before[$field] ?? null) ? ($before[$field] ?? '—') : json_encode($before[$field])) ?></p>
                                                <p class="mt-1 text-gray-700">After: <?= $h(is_scalar($value) ? $value : json_encode($value)) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
<script>
(() => {
    const sourceType = document.getElementById('source_type_id');
    if (!sourceType) return;

    const groups = {
        externalOffice: document.getElementById('externalOfficeFields'),
        hospital: document.getElementById('hospitalFields'),
        spMember: document.getElementById('spMemberFields'),
        agency: document.getElementById('agencyNameField'),
        client: document.getElementById('clientNameField'),
        municipality: document.getElementById('municipalityField'),
        contact: document.getElementById('contactFields')
    };
    const controls = {
        externalOffice: document.getElementById('external_office_id'),
        hospital: document.getElementById('hospital_id'),
        spMember: document.getElementById('sp_member_id'),
        agency: document.getElementById('source_name_agency'),
        client: document.getElementById('source_name_client'),
        municipality: document.getElementById('municipality_id'),
        contactNumber: document.getElementById('source_contact_number'),
        liaison: document.getElementById('source_liaison_name'),
        address: document.getElementById('source_address')
    };

    function setVisibleAndEnabled(group, control, visible, required = false) {
        group.classList.toggle('hidden', !visible);
        if (group === groups.contact) group.classList.toggle('grid', visible);
        if (control) {
            control.disabled = !visible;
            control.required = visible && required;
        }
    }

    function applySourceType(clearPrevious) {
        const selectedOption = sourceType.options[sourceType.selectedIndex];
        const name = selectedOption ? selectedOption.dataset.sourceName : '';

        if (clearPrevious) {
            Object.values(controls).forEach((control) => {
                if (control.tagName === 'SELECT') control.value = '';
                else control.value = '';
            });
        }

        const provincial = name === 'Provincial Government Offices';
        const hospital = name === 'Hospital';
        const spMember = name === 'SP Member';
        const agency = name === 'Agency';
        const client = name === 'Client';
        const knownSource = provincial || hospital || spMember || agency || client;

        setVisibleAndEnabled(groups.externalOffice, controls.externalOffice, provincial, provincial);
        setVisibleAndEnabled(groups.hospital, controls.hospital, hospital, hospital);
        setVisibleAndEnabled(groups.spMember, controls.spMember, spMember, spMember);
        setVisibleAndEnabled(groups.agency, controls.agency, agency, agency);
        setVisibleAndEnabled(groups.client, controls.client, client, client);
        setVisibleAndEnabled(groups.municipality, controls.municipality, client, client);
        groups.contact.classList.toggle('hidden', !knownSource);
        groups.contact.classList.toggle('grid', knownSource);
        [controls.contactNumber, controls.liaison, controls.address].forEach((control) => {
            control.disabled = !knownSource;
        });
    }

    sourceType.addEventListener('change', () => applySourceType(true));
    applySourceType(false);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
