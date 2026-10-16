<!-- Page Header -->
<section class="bg-gradient-to-r from-blue-600 to-blue-700 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 text-primary-100 mb-3">
            <a href="<?= BASE_URL ?>/public/portal" class="hover:text-white transition-colors">
                <i class="fa-solid fa-home"></i> Portal
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <span>SP Members</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3">Sangguniang Panlalawigan Members</h1>
        <p class="text-primary-100 text-lg max-w-3xl">
            Meet the elected officials serving the Province of Pangasinan. View their profiles, 
            positions, and district representations.
        </p>
    </div>
</section>

<!-- Search and Filters -->
<section class="bg-white border-b border-gray-200 sticky top-16 z-40 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <form method="GET" action="<?= BASE_URL ?>/public/portal/members" class="space-y-4">
            
            <!-- Search Bar -->
            <div class="flex gap-3">
                <div class="flex-1 relative">
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search ?? '') ?>"
                        placeholder="Search by name or position..." 
                        class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                    />
                </div>
                <button 
                    type="submit" 
                    class="px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-hover transition-colors shadow-sm">
                    <i class="fa-solid fa-search mr-2"></i>Search
                </button>
            </div>
            
            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                
                <select 
                    name="district" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-sm">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $district): ?>
                        <option 
                            value="<?= $district['id'] ?>" 
                            <?= ($districtId ?? '') == $district['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($district['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select 
                    name="position" 
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-sm">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $pos): ?>
                        <option 
                            value="<?= htmlspecialchars($pos) ?>" 
                            <?= ($position ?? '') === $pos ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pos) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if (!empty($search) || !empty($districtId) || !empty($position)): ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal/members" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                        <i class="fa-solid fa-times mr-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
                
            </div>
            
        </form>
    </div>
</section>

<!-- Members Count -->
<section class="bg-gray-50 py-4 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">
                <i class="fa-solid fa-users mr-2"></i>
                Showing <span class="font-semibold text-gray-900"><?= count($members) ?></span> 
                <?= count($members) === 1 ? 'member' : 'members' ?>
                <?php if (!empty($search)): ?>
                    <span class="text-gray-500">for "<?= htmlspecialchars($search) ?>"</span>
                <?php endif; ?>
            </p>
            <?php if (!empty($members) && count($members) > 1): ?>
                <p class="text-xs text-gray-500 hidden sm:block">
                    <i class="fa-solid fa-sort-amount-down mr-1"></i>Sorted by order and name
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Members Grid -->
<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (empty($members)): ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary-100 text-primary-400 mb-6">
                    <i class="fa-solid fa-user-slash text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Members Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    <?php if (!empty($search) || !empty($districtId) || !empty($position)): ?>
                        We couldn't find any members matching your search criteria. 
                        Try adjusting your filters or search terms.
                    <?php else: ?>
                        There are currently no active SP members to display. 
                        Please check back later or contact the office for more information.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || !empty($districtId) || !empty($position)): ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal/members" 
                        class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-hover transition-colors shadow-md">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                        View All Members
                    </a>
                <?php else: ?>
                    <a 
                        href="<?= BASE_URL ?>/public/portal/contact" 
                        class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-hover transition-colors shadow-md">
                        <i class="fa-solid fa-envelope"></i>
                        Contact Us
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Members Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                
                <?php foreach ($members as $member): ?>
                    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all card-hover border border-gray-200 overflow-hidden">
                        
                        <!-- Member Photo -->
                        <div class="aspect-square bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center overflow-hidden">
                            <?php if (!empty($member['photo_path'])): ?>
                                <img 
                                    src="<?= BASE_URL ?>/<?= htmlspecialchars($member['photo_path']) ?>" 
                                    alt="<?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>" 
                                    class="w-full h-full object-cover"
                                    onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fa-solid fa-user text-6xl text-blue-300\'></i>';"
                                />
                            <?php else: ?>
                                <i class="fa-solid fa-user text-6xl text-blue-300"></i>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Member Info -->
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-900 mb-1 leading-tight">
                                <?php
                                $fullName = trim(
                                    $member['first_name'] . ' ' . 
                                    (!empty($member['middle_name']) ? mb_substr($member['middle_name'], 0, 1) . '. ' : '') . 
                                    $member['last_name'] . 
                                    (!empty($member['suffix']) ? ' ' . $member['suffix'] : '')
                                );
                                echo htmlspecialchars($fullName);
                                ?>
                            </h3>
                            
                            <div class="flex items-start gap-2 mb-3">
                                <i class="fa-solid fa-briefcase text-primary-600 text-sm mt-0.5"></i>
                                <p class="text-sm font-medium text-gray-700 leading-snug">
                                    <?= htmlspecialchars($member['position']) ?>
                                </p>
                            </div>
                            
                            <?php if (!empty($member['district_name'])): ?>
                                <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
                                    <i class="fa-solid fa-map-marker-alt text-gray-400 text-sm"></i>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($member['district_name']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
                
            </div>
        <?php endif; ?>
        
    </div>
</section>

<!-- Information Notice -->
<section class="py-12 bg-white border-t border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-primary-50 border border-primary-200 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-circle-info text-2xl text-primary-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">About the Members Directory</h3>
                    <p class="text-gray-700 leading-relaxed mb-3">
                        This directory shows the current active members of the Sangguniang Panlalawigan of Pangasinan. 
                        Each member represents their respective district or serves in an ex-officio capacity.
                    </p>
                    <p class="text-sm text-gray-600">
                        For official inquiries or to contact a specific member's office, please call the Provincial Capitol 
                        at <span class="font-semibold">(075) 542-5000</span> or visit during office hours.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
