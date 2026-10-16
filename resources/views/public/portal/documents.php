<!-- Page Header -->
<section class="bg-gradient-to-r from-green-600 to-green-700 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 text-green-100 mb-3">
            <a href="<?= BASE_URL ?>/public/portal" class="hover:text-white transition-colors">
                <i class="fa-solid fa-home"></i> Portal
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <span>Legislative Records</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3">Legislative Records</h1>
        <p class="text-green-100 text-lg max-w-3xl">
            Browse and search approved ordinances, resolutions, and other legislative documents 
            enacted by the Sangguniang Panlalawigan.
        </p>
    </div>
</section>

<!-- Search and Filters -->
<section class="bg-white border-b border-gray-200 sticky top-16 z-40 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <form method="GET" action="<?= BASE_URL ?>/public/portal/documents" class="space-y-4">
            
            <!-- Search Bar -->
            <div class="flex gap-3">
                <div class="flex-1 relative">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search ?? '') ?>"
                        placeholder="Search by number, subject, description, or tags..." 
                        class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                    />
                </div>
                <button 
                    type="submit" 
                    class="px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition-colors shadow-sm">
                    <i class="fa-solid fa-search mr-2"></i>Search
                </button>
            </div>
            
            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                
                <select 
                    name="type" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                    <option value="">All Document Types</option>
                    <?php foreach ($documentTypes as $docType): ?>
                        <option 
                            value="<?= $docType['id'] ?>" 
                            <?= ($typeId ?? '') == $docType['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($docType['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select 
                    name="year" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                    <option value="">All Years</option>
                    <?php foreach ($years as $yr): ?>
                        <option 
                            value="<?= $yr ?>" 
                            <?= ($year ?? '') == $yr ? 'selected' : '' ?>>
                            <?= htmlspecialchars($yr) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if (!empty($search) || !empty($typeId) || !empty($year)): ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal/documents" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                        <i class="fa-solid fa-times mr-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
                
            </div>
            
        </form>
    </div>
</section>

<!-- Documents Count and Pagination Info -->
<section class="bg-gray-50 py-4 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">
            <i class="fa-solid fa-file-lines mr-2"></i>
            <?php if ($totalRecords > 0): ?>
                Showing 
                <span class="font-semibold text-gray-900">
                    <?= (($page - 1) * $perPage + 1) ?>
                </span>
                - 
                <span class="font-semibold text-gray-900">
                    <?= min($page * $perPage, $totalRecords) ?>
                </span>
                of 
                <span class="font-semibold text-gray-900"><?= $totalRecords ?></span> 
                <?= $totalRecords === 1 ? 'record' : 'records' ?>
                <?php if (!empty($search)): ?>
                    <span class="text-gray-500">for "<?= htmlspecialchars($search) ?>"</span>
                <?php endif; ?>
            <?php else: ?>
                No records found
            <?php endif; ?>
        </p>
        
        <?php if ($totalPages > 1): ?>
            <p class="text-sm text-gray-600">
                Page <span class="font-semibold text-gray-900"><?= $page ?></span> of 
                <span class="font-semibold text-gray-900"><?= $totalPages ?></span>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- Documents List -->
<section class="py-8 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (empty($documents)): ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 text-green-400 mb-6">
                    <i class="fa-solid fa-file-circle-question text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Records Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    <?php if (!empty($search) || !empty($typeId) || !empty($year)): ?>
                        We couldn't find any legislative records matching your search criteria. 
                        Try adjusting your filters or search terms.
                    <?php else: ?>
                        There are currently no legislative records to display. 
                        Please check back later for published documents.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || !empty($typeId) || !empty($year)): ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal/documents" 
                        class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition-colors shadow-md">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                        View All Records
                    </a>
                <?php else: ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal" 
                        class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition-colors shadow-md">
                        <i class="fa-solid fa-home"></i>
                        Back to Portal Home
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Documents Cards -->
            <div class="space-y-4">
                
                <?php foreach ($documents as $doc): ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal/documents/view?id=<?= $doc['id'] ?>"
                        class="block bg-white rounded-xl shadow-sm hover:shadow-lg transition-all border border-gray-200 overflow-hidden card-hover">
                        
                        <div class="p-6">
                            <div class="flex flex-wrap items-start gap-4">
                                
                                <!-- Document Icon -->
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-16 h-16 rounded-xl bg-gradient-to-br from-green-100 to-green-50 text-green-600">
                                        <i class="fa-solid fa-file-lines text-2xl"></i>
                                    </div>
                                </div>
                                
                                <!-- Document Details -->
                                <div class="flex-1 min-w-0">
                                    
                                    <!-- Document Type Badge and Date -->
                                    <div class="flex flex-wrap items-center gap-3 mb-3">
                                        <?php
                                        $badgeColor = $doc['document_type_color'] ?? 'gray';
                                        $badgeClasses = [
                                            'blue' => 'bg-primary-100 text-primary-700 border-primary-200',
                                            'green' => 'bg-green-100 text-green-700 border-green-200',
                                            'yellow' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                            'red' => 'bg-red-100 text-red-700 border-red-200',
                                            'purple' => 'bg-purple-100 text-purple-700 border-purple-200',
                                            'pink' => 'bg-pink-100 text-pink-700 border-pink-200',
                                            'indigo' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                            'gray' => 'bg-gray-100 text-gray-700 border-gray-200',
                                        ];
                                        $badgeClass = $badgeClasses[$badgeColor] ?? $badgeClasses['gray'];
                                        ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border <?= $badgeClass ?>">
                                            <i class="fa-solid fa-tag"></i>
                                            <?= htmlspecialchars($doc['document_type_name']) ?>
                                        </span>
                                        
                                        <span class="text-sm text-gray-500">
                                            <i class="fa-solid fa-calendar-check mr-1.5"></i>
                                            <?= date('F d, Y', strtotime($doc['date_approved'])) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Approved Number -->
                                    <div class="text-sm font-semibold text-green-600 mb-2">
                                        <?= htmlspecialchars($doc['approved_no']) ?>
                                    </div>
                                    
                                    <!-- Subject Matter -->
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 leading-snug">
                                        <?= htmlspecialchars($doc['subject_matter']) ?>
                                    </h3>
                                    
                                    <!-- Description Preview -->
                                    <?php if (!empty($doc['description'])): ?>
                                        <p class="text-sm text-gray-600 leading-relaxed line-clamp-2 mb-3">
                                            <?= htmlspecialchars(mb_substr(strip_tags($doc['description']), 0, 200)) ?>
                                            <?= mb_strlen(strip_tags($doc['description'])) > 200 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Tags -->
                                    <?php if (!empty($doc['tags'])): ?>
                                        <div class="flex flex-wrap gap-2 mt-3">
                                            <?php
                                            $tags = array_filter(array_map('trim', explode(',', $doc['tags'])));
                                            $displayTags = array_slice($tags, 0, 4);
                                            $remainingCount = count($tags) - count($displayTags);
                                            ?>
                                            <?php foreach ($displayTags as $tag): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                                    <i class="fa-solid fa-hashtag mr-1 text-gray-400"></i>
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if ($remainingCount > 0): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-500">
                                                    +<?= $remainingCount ?> more
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                </div>
                                
                                <!-- View Arrow -->
                                <div class="flex-shrink-0 self-center">
                                    <i class="fa-solid fa-chevron-right text-gray-400"></i>
                                </div>
                                
                            </div>
                        </div>
                        
                    </a>
                <?php endforeach; ?>
                
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex items-center justify-center gap-2">
                    
                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                        <a 
                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <a 
                            href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                            1
                        </a>
                        <?php if ($startPage > 2): ?>
                            <span class="text-gray-400">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg border-2 border-green-600 bg-green-600 text-white font-semibold">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a 
                                href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="text-gray-400">...</span>
                        <?php endif; ?>
                        <a 
                            href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" 
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                            <?= $totalPages ?>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <?php if ($page < $totalPages): ?>
                        <a 
                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed">
                            <i class="fa-solid fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                    
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
    </div>
</section>

<!-- Information Notice -->
<section class="py-12 bg-white border-t border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-green-50 border border-green-200 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-circle-info text-2xl text-green-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">About Legislative Records</h3>
                    <p class="text-gray-700 leading-relaxed mb-3">
                        This portal provides access to approved and officially archived legislative records including 
                        ordinances, resolutions, and other documents enacted by the Sangguniang Panlalawigan of Pangasinan.
                    </p>
                    <p class="text-sm text-gray-600 mb-3">
                        All displayed records have been officially approved and are available for public viewing. 
                        Click on any record to view its full details and download available documents.
                    </p>
                    <p class="text-sm text-gray-600">
                        For certified copies or official requests, please contact the Provincial Capitol Office 
                        at <span class="font-semibold">(075) 542-5000</span>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
