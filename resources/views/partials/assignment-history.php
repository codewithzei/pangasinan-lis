<section class="rounded-2xl border border-gray-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-gray-900">Assignment History</h2>

    <?php if (empty($assignments)): ?>
        <div class="mt-4 py-8 text-center">
            <p class="text-sm text-gray-500">No assignment records found.</p>
        </div>
    <?php else: ?>
        <div class="mt-4 space-y-3 sm:hidden">
            <?php foreach ($assignments as $assignment): ?>
                <article class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $assignment['phase'] ?? '')))) ?>
                            </p>
                            <p class="mt-1 break-words text-sm font-semibold text-gray-800">
                                <?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—') ?>
                            </p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                            <?= htmlspecialchars(ucwords(strtolower($assignment['decision'] ?? '—'))) ?>
                        </span>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <dt class="text-gray-400">Assigned</dt>
                            <dd class="mt-0.5 break-words text-gray-600">
                                <?= !empty($assignment['created_at'])
                                    ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['created_at'])))
                                    : '—' ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-400">Accepted By</dt>
                            <dd class="mt-0.5 break-words font-medium text-gray-700">
                                <?= htmlspecialchars($assignment['accepted_by_username'] ?? '—') ?>
                                <?php if (!empty(trim($assignment['accepted_by_fullname'] ?? ''))): ?>
                                    <span class="block font-normal text-gray-400"><?= htmlspecialchars(trim($assignment['accepted_by_fullname'])) ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-400">Assigned By</dt>
                            <dd class="mt-0.5 break-words text-gray-600">
                                <?= htmlspecialchars($assignment['assigned_by_username'] ?? '—') ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-400">Received</dt>
                            <dd class="mt-0.5 break-words text-gray-600">
                                <?= !empty($assignment['received_at'])
                                    ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['received_at'])))
                                    : '—' ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-400">Completed</dt>
                            <dd class="mt-0.5 break-words text-gray-600">
                                <?= !empty($assignment['completed_at'])
                                    ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['completed_at'])))
                                    : '—' ?>
                            </dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 hidden overflow-x-auto sm:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Phase</th>
                        <th class="px-4 py-3 font-medium">Decision</th>
                        <th class="px-4 py-3 font-medium">Assigned</th>
                        <th class="px-4 py-3 font-medium">Accepted By</th>
                        <th class="px-4 py-3 font-medium">Assigned By</th>
                        <th class="px-4 py-3 font-medium">Received</th>
                        <th class="px-4 py-3 font-medium">Completed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($assignments as $assignment): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-xs font-medium text-gray-700">
                                <?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $assignment['phase'] ?? '')))) ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700">
                                <?= htmlspecialchars(ucwords(strtolower($assignment['decision'] ?? '—'))) ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                <?= !empty($assignment['created_at'])
                                    ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['created_at'])))
                                    : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700">
                                <?= htmlspecialchars($assignment['accepted_by_username'] ?? '—') ?>
                                <?php if (!empty(trim($assignment['accepted_by_fullname'] ?? ''))): ?>
                                    <span class="block text-gray-400"><?= htmlspecialchars(trim($assignment['accepted_by_fullname'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700">
                                <?= htmlspecialchars($assignment['assigned_by_username'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                <?= !empty($assignment['received_at'])
                                    ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['received_at'])))
                                    : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                <?= !empty($assignment['completed_at'])
                                    ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['completed_at'])))
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
