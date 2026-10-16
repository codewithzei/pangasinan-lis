<!-- Page Header -->
<section class="bg-gradient-to-r from-green-600 to-green-700 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 text-green-100 mb-3">
            <a href="<?= BASE_URL ?>/public/portal" class="hover:text-white transition-colors">
                <i class="fa-solid fa-home"></i> Portal
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <a href="<?= BASE_URL ?>/public/portal/documents" class="hover:text-white transition-colors">
                Legislative Records
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <span>Document Detail</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold">Legislative Document</h1>
    </div>
</section>

<!-- Document Details -->
<section class="py-12 bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Back Button -->
        <div class="mb-6">
            <a 
                href="<?= BASE_URL ?>/public/portal/documents" 
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Records
            </a>
        </div>
        
        <!-- Main Document Card -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-green-50 to-green-100 border-b border-green-200 p-6">
                
                <!-- Document Type Badge -->
                <?php
                $badgeColor = $document['document_type_color'] ?? 'gray';
                $badgeClasses = [
                    'blue' => 'bg-primary-100 text-primary-700 border-primary-300',
                    'green' => 'bg-green-100 text-green-700 border-green-300',
                    'yellow' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                    'red' => 'bg-red-100 text-red-700 border-red-300',
                    'purple' => 'bg-purple-100 text-purple-700 border-purple-300',
                    'pink' => 'bg-pink-100 text-pink-700 border-pink-300',
                    'indigo' => 'bg-indigo-100 text-indigo-700 border-indigo-300',
                    'gray' => 'bg-gray-100 text-gray-700 border-gray-300',
                ];
                $badgeClass = $badgeClasses[$badgeColor] ?? $badgeClasses['gray'];
                ?>
                <div class="mb-4">
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold border-2 <?= $badgeClass ?>">
                        <i class="fa-solid fa-tag"></i>
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                </div>
                
                <!-- Approved Number -->
                <div class="text-lg font-bold text-green-700 mb-3">
                    <?= htmlspecialchars($document['approved_no']) ?>
                </div>
                
                <!-- Subject Matter -->
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight">
                    <?= htmlspecialchars($document['subject_matter']) ?>
                </h2>
                
            </div>
            
            <!-- Metadata Section -->
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    
                    <div>
                        <div class="flex items-center gap-2 text-sm font-medium text-gray-500 mb-2">
                            <i class="fa-solid fa-calendar-check text-green-600"></i>
                            Date Approved
                        </div>
                        <div class="text-base font-semibold text-gray-900">
                            <?= date('F d, Y', strtotime($document['date_approved'])) ?>
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex items-center gap-2 text-sm font-medium text-gray-500 mb-2">
                            <i class="fa-solid fa-clock text-green-600"></i>
                            Archived On
                        </div>
                        <div class="text-base font-semibold text-gray-900">
                            <?= date('F d, Y', strtotime($document['created_at'])) ?>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Description Section -->
            <?php if (!empty($document['description'])): ?>
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-align-left text-green-600"></i>
                        Description
                    </h3>
                    <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed">
                        <?= nl2br(htmlspecialchars($document['description'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Authors Section -->
            <?php if (!empty($authors)): ?>
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-user-pen text-green-600"></i>
                        Authors
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($authors as $author): ?>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-green-100 text-green-600 flex-shrink-0">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">
                                        <?php
                                        $fullName = trim(
                                            $author['first_name'] . ' ' . 
                                            (!empty($author['middle_name']) ? mb_substr($author['middle_name'], 0, 1) . '. ' : '') . 
                                            $author['last_name'] . 
                                            (!empty($author['suffix']) ? ' ' . $author['suffix'] : '')
                                        );
                                        echo htmlspecialchars($fullName);
                                        ?>
                                    </div>
                                    <div class="text-sm text-gray-600 truncate">
                                        <?= htmlspecialchars($author['position']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Committees Section -->
            <?php if (!empty($committees)): ?>
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-sitemap text-green-600"></i>
                        Committees Involved
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($committees as $committee): ?>
                            <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
                                <div class="font-semibold text-gray-900 mb-1">
                                    <?= htmlspecialchars($committee['name']) ?>
                                </div>
                                <?php if (!empty($committee['description'])): ?>
                                    <div class="text-sm text-gray-600">
                                        <?= htmlspecialchars($committee['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tags Section -->
            <?php if (!empty($document['tags'])): ?>
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-tags text-green-600"></i>
                        Tags
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $tags = array_filter(array_map('trim', explode(',', $document['tags'])));
                        foreach ($tags as $tag):
                        ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                <i class="fa-solid fa-hashtag mr-1.5 text-gray-400"></i>
                                <?= htmlspecialchars($tag) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Attachments Section -->
            <?php if (!empty($attachments)): ?>
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-paperclip text-green-600"></i>
                        Attachments
                        <span class="text-sm font-normal text-gray-500">(<?= count($attachments) ?>)</span>
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($attachments as $attachment): ?>
                            <a 
                                href="<?= BASE_URL ?>/public/portal/download-attachment?id=<?= $attachment['id'] ?>" 
                                class="flex items-center gap-4 p-4 bg-primary-50 hover:bg-primary-100 rounded-lg border border-primary-200 transition-colors group">
                                
                                <!-- File Icon -->
                                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary-100 text-primary-600 flex-shrink-0 group-hover:bg-blue-200 transition-colors">
                                    <?php
                                    $ext = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                    $iconMap = [
                                        'pdf' => 'fa-file-pdf',
                                        'doc' => 'fa-file-word',
                                        'docx' => 'fa-file-word',
                                        'xls' => 'fa-file-excel',
                                        'xlsx' => 'fa-file-excel',
                                        'ppt' => 'fa-file-powerpoint',
                                        'pptx' => 'fa-file-powerpoint',
                                        'jpg' => 'fa-file-image',
                                        'jpeg' => 'fa-file-image',
                                        'png' => 'fa-file-image',
                                        'gif' => 'fa-file-image',
                                        'zip' => 'fa-file-zipper',
                                        'rar' => 'fa-file-zipper',
                                    ];
                                    $icon = $iconMap[$ext] ?? 'fa-file';
                                    ?>
                                    <i class="fa-solid <?= $icon ?> text-2xl"></i>
                                </div>
                                
                                <!-- File Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-900 truncate group-hover:text-primary-700 transition-colors">
                                        <?= htmlspecialchars($attachment['file_name']) ?>
                                    </div>
                                    <div class="text-sm text-gray-600 flex items-center gap-3 mt-1">
                                        <span>
                                            <?php
                                            $size = $attachment['file_size'];
                                            if ($size < 1024) {
                                                echo $size . ' B';
                                            } elseif ($size < 1048576) {
                                                echo round($size / 1024, 2) . ' KB';
                                            } else {
                                                echo round($size / 1048576, 2) . ' MB';
                                            }
                                            ?>
                                        </span>
                                        <span class="text-gray-400">•</span>
                                        <span><?= date('M d, Y', strtotime($attachment['uploaded_at'])) ?></span>
                                    </div>
                                </div>
                                
                                <!-- Download Icon -->
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-600 text-white group-hover:bg-primary-hover transition-colors">
                                        <i class="fa-solid fa-download"></i>
                                    </div>
                                </div>
                                
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-6">
                    <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                        <i class="fa-solid fa-paperclip text-3xl text-gray-300 mb-3"></i>
                        <p class="text-sm text-gray-500">No attachments available for this document.</p>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Action Buttons -->
        <div class="mt-6 flex flex-wrap gap-3 justify-center">
            <a 
                href="<?= BASE_URL ?>/public/portal/documents" 
                class="inline-flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Records
            </a>
        </div>
        
    </div>
</section>

<!-- Related Information -->
<section class="py-12 bg-white border-t border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-primary-50 border border-primary-200 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-circle-info text-2xl text-primary-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Need More Information?</h3>
                    <p class="text-gray-700 leading-relaxed mb-3">
                        This is an official archived record of a legislative document approved by the Sangguniang Panlalawigan. 
                        All information displayed is for public access and informational purposes.
                    </p>
                    <p class="text-sm text-gray-600 mb-4">
                        If you need a certified copy, have questions about implementation, or require additional documentation, 
                        please contact the Provincial Capitol Office.
                    </p>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-phone text-primary-600"></i>
                            <span class="font-semibold text-gray-900">(075) 542-5000</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-envelope text-primary-600"></i>
                            <span class="font-semibold text-gray-900">info@pangasinan.gov.ph</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
