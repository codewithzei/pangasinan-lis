<!-- Page Header -->
<section class="bg-gradient-to-r from-purple-600 to-purple-700 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 text-purple-100 mb-3">
            <a href="<?= BASE_URL ?>/public/portal" class="hover:text-white transition-colors">
                <i class="fa-solid fa-home"></i> Portal
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <span>Committees</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3">Legislative Committees</h1>
        <p class="text-purple-100 text-lg max-w-3xl">
            Learn about the various committees of the Sangguniang Panlalawigan and their areas 
            of oversight and responsibility in provincial governance.
        </p>
    </div>
</section>

<!-- Committees Count -->
<section class="bg-gray-50 py-4 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-gray-600">
            <i class="fa-solid fa-sitemap mr-2"></i>
            <span class="font-semibold text-gray-900"><?= count($committees) ?></span> 
            Active <?= count($committees) === 1 ? 'Committee' : 'Committees' ?>
        </p>
    </div>
</section>

<!-- Committees List -->
<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (empty($committees)): ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-purple-100 text-purple-400 mb-6">
                    <i class="fa-solid fa-sitemap text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Committees Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    There are currently no active committees to display. 
                    Please check back later or contact the office for more information.
                </p>
                <a 
                    href="<?= BASE_URL ?>/public/portal/contact" 
                    class="inline-flex items-center gap-2 px-6 py-3 bg-purple-600 text-white font-semibold rounded-xl hover:bg-purple-700 transition-colors shadow-md">
                    <i class="fa-solid fa-envelope"></i>
                    Contact Us
                </a>
            </div>
        <?php else: ?>
            <!-- Committees Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <?php foreach ($committees as $index => $committee): ?>
                    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all border border-gray-200 overflow-hidden card-hover">
                        
                        <!-- Committee Header -->
                        <div class="bg-gradient-to-r from-purple-50 to-purple-100 border-b border-purple-200 p-6">
                            <div class="flex items-start gap-4">
                                <!-- Committee Icon -->
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-purple-600 text-white">
                                        <?php
                                        // Assign different icons based on committee name keywords
                                        $name = strtolower($committee['name']);
                                        $icon = 'fa-sitemap'; // default
                                        
                                        if (strpos($name, 'health') !== false) $icon = 'fa-heart-pulse';
                                        elseif (strpos($name, 'education') !== false) $icon = 'fa-graduation-cap';
                                        elseif (strpos($name, 'finance') !== false || strpos($name, 'budget') !== false) $icon = 'fa-coins';
                                        elseif (strpos($name, 'agriculture') !== false) $icon = 'fa-wheat-awn';
                                        elseif (strpos($name, 'infrastructure') !== false || strpos($name, 'public works') !== false) $icon = 'fa-road';
                                        elseif (strpos($name, 'environment') !== false) $icon = 'fa-leaf';
                                        elseif (strpos($name, 'tourism') !== false) $icon = 'fa-plane';
                                        elseif (strpos($name, 'trade') !== false || strpos($name, 'industry') !== false) $icon = 'fa-industry';
                                        elseif (strpos($name, 'social') !== false || strpos($name, 'welfare') !== false) $icon = 'fa-people-group';
                                        elseif (strpos($name, 'legal') !== false || strpos($name, 'justice') !== false) $icon = 'fa-gavel';
                                        elseif (strpos($name, 'transportation') !== false) $icon = 'fa-bus';
                                        elseif (strpos($name, 'energy') !== false || strpos($name, 'power') !== false) $icon = 'fa-bolt';
                                        elseif (strpos($name, 'peace') !== false || strpos($name, 'order') !== false) $icon = 'fa-shield-halved';
                                        elseif (strpos($name, 'youth') !== false) $icon = 'fa-children';
                                        elseif (strpos($name, 'senior') !== false || strpos($name, 'elderly') !== false) $icon = 'fa-person-cane';
                                        elseif (strpos($name, 'housing') !== false) $icon = 'fa-house';
                                        elseif (strpos($name, 'labor') !== false || strpos($name, 'employment') !== false) $icon = 'fa-briefcase';
                                        ?>
                                        <i class="fa-solid <?= $icon ?> text-2xl"></i>
                                    </div>
                                </div>
                                
                                <!-- Committee Name -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-xl font-bold text-gray-900 leading-tight mb-1">
                                        <?= htmlspecialchars($committee['name']) ?>
                                    </h3>
                                    <div class="text-sm text-purple-600 font-medium">
                                        Committee #<?= $index + 1 ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Committee Description -->
                        <div class="p-6">
                            <?php if (!empty($committee['description'])): ?>
                                <div class="flex items-start gap-3 mb-4">
                                    <i class="fa-solid fa-info-circle text-purple-600 mt-1 flex-shrink-0"></i>
                                    <p class="text-gray-700 leading-relaxed">
                                        <?= htmlspecialchars($committee['description']) ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="flex items-start gap-3 mb-4">
                                    <i class="fa-solid fa-info-circle text-gray-400 mt-1 flex-shrink-0"></i>
                                    <p class="text-gray-500 italic">
                                        No description available for this committee.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Committee Areas Badge -->
                            <div class="pt-4 border-t border-gray-200">
                                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-purple-50 text-purple-700 text-sm font-medium border border-purple-200">
                                    <i class="fa-solid fa-layer-group"></i>
                                    Legislative Oversight Committee
                                </div>
                            </div>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
                
            </div>
        <?php endif; ?>
        
    </div>
</section>

<!-- About Committees Section -->
<section class="py-16 bg-white border-t border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">About Legislative Committees</h2>
            <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                Committees are essential working groups within the Sangguniang Panlalawigan that focus 
                on specific areas of governance and policy-making.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- Committee Functions -->
            <div class="bg-purple-50 rounded-xl p-6 border border-purple-200">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-purple-600 text-white">
                        <i class="fa-solid fa-clipboard-list text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Committee Functions</h3>
                </div>
                <ul class="space-y-3 text-gray-700">
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-check-circle text-purple-600 mt-1 flex-shrink-0"></i>
                        <span>Review and study proposed ordinances and resolutions within their jurisdiction</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-check-circle text-purple-600 mt-1 flex-shrink-0"></i>
                        <span>Conduct public hearings and consultations on legislative matters</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-check-circle text-purple-600 mt-1 flex-shrink-0"></i>
                        <span>Provide recommendations and reports to the plenary body</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-check-circle text-purple-600 mt-1 flex-shrink-0"></i>
                        <span>Monitor implementation of laws within their specific areas</span>
                    </li>
                </ul>
            </div>
            
            <!-- Committee Process -->
            <div class="bg-primary-50 rounded-xl p-6 border border-primary-200">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary-600 text-white">
                        <i class="fa-solid fa-route text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Legislative Process</h3>
                </div>
                <ul class="space-y-3 text-gray-700">
                    <li class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold mt-0.5 flex-shrink-0">
                            1
                        </div>
                        <span>Documents are referred to relevant committees for review</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold mt-0.5 flex-shrink-0">
                            2
                        </div>
                        <span>Committees conduct hearings and gather input from stakeholders</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold mt-0.5 flex-shrink-0">
                            3
                        </div>
                        <span>Committee reports with recommendations are submitted</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold mt-0.5 flex-shrink-0">
                            4
                        </div>
                        <span>Plenary session considers and votes on the measure</span>
                    </li>
                </ul>
            </div>
            
        </div>
        
    </div>
</section>

<!-- Information Notice -->
<section class="py-12 bg-gray-50 border-t border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-circle-info text-2xl text-purple-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Committee Membership & Leadership</h3>
                    <p class="text-gray-700 leading-relaxed mb-3">
                        Each committee is composed of selected members of the Sangguniang Panlalawigan, 
                        with a chairperson who leads the committee's activities and proceedings. Committee 
                        assignments are based on members' expertise and interests in specific policy areas.
                    </p>
                    <p class="text-sm text-gray-600">
                        For inquiries about committee membership, hearing schedules, or to participate in 
                        committee proceedings, please contact the Provincial Capitol Office at 
                        <span class="font-semibold">(075) 542-5000</span>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-12 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h3 class="text-2xl font-bold text-gray-900 mb-4">Stay Informed About Legislative Work</h3>
        <p class="text-gray-600 mb-8 max-w-2xl mx-auto">
            Explore approved legislative documents and learn how committees contribute to 
            provincial governance and policy-making.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a 
                href="<?= BASE_URL ?>/public/portal/documents" 
                class="inline-flex items-center gap-2 px-6 py-3 bg-purple-600 text-white font-semibold rounded-xl hover:bg-purple-700 transition-colors shadow-md">
                <i class="fa-solid fa-file-lines"></i>
                Browse Legislative Records
            </a>
            <a 
                href="<?= BASE_URL ?>/public/portal/members" 
                class="inline-flex items-center gap-2 px-6 py-3 bg-white text-gray-700 border-2 border-gray-300 font-semibold rounded-xl hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-users"></i>
                View SP Members
            </a>
        </div>
    </div>
</section>
