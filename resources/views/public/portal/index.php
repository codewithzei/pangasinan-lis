<!-- Hero Section -->
<section class="hero-gradient text-white py-20 official-seal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/20 backdrop-blur-sm text-white text-sm font-semibold mb-6">
                <i class="fa-solid fa-landmark"></i>
                Official Provincial Legislative Portal
            </div>
            
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight mb-6">
                Pangasinan Provincial<br>Legislative Information System
            </h1>
            
            <p class="text-xl text-primary-100 max-w-3xl mx-auto mb-8 leading-relaxed">
                Access public legislative records, learn about your elected officials, and stay informed about 
                the work of the Sangguniang Panlalawigan of Pangasinan.
            </p>
            
            <div class="flex flex-wrap justify-center gap-4">
                <a href="<?= BASE_URL ?>/public/portal/documents" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-white text-primary-700 font-semibold rounded-xl shadow-lg hover:shadow-xl hover:bg-primary-50 transition-all">
                    <i class="fa-solid fa-file-lines"></i>
                    Browse Legislative Records
                </a>
                <a href="<?= BASE_URL ?>/public/portal/members" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-primary-700 text-white font-semibold rounded-xl shadow-lg hover:bg-primary-800 transition-all border-2 border-primary-500">
                    <i class="fa-solid fa-users"></i>
                    View SP Members
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="py-12 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-100 text-primary-600 mb-4">
                    <i class="fa-solid fa-users text-2xl"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-2">
                    <?= $stats['members_count'] ?? 0 ?>
                </div>
                <div class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                    SP Members
                </div>
            </div>
            
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-4">
                    <i class="fa-solid fa-file-lines text-2xl"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-2">
                    <?= $stats['documents_count'] ?? 0 ?>
                </div>
                <div class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                    Legislative Records
                </div>
            </div>
            
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-purple-100 text-purple-600 mb-4">
                    <i class="fa-solid fa-sitemap text-2xl"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-2">
                    <?= $stats['committees_count'] ?? 0 ?>
                </div>
                <div class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                    Active Committees
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- Quick Access Section -->
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Explore Public Information</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Access legislative records, learn about your representatives, and stay informed about provincial governance.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- SP Members Card -->
            <a href="<?= BASE_URL ?>/public/portal/members" 
               class="bg-white rounded-xl p-6 shadow-md hover:shadow-xl transition-all card-hover border border-gray-200">
                <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-primary-100 text-primary-600 mb-4">
                    <i class="fa-solid fa-users text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">SP Members</h3>
                <p class="text-sm text-gray-600 leading-relaxed mb-4">
                    View the current members of the Sangguniang Panlalawigan and their district representations.
                </p>
                <div class="flex items-center text-sm font-semibold text-primary-600">
                    View Directory
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                </div>
            </a>
            
            <!-- Legislative Records Card -->
            <a href="<?= BASE_URL ?>/public/portal/documents" 
               class="bg-white rounded-xl p-6 shadow-md hover:shadow-xl transition-all card-hover border border-gray-200">
                <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-green-100 text-green-600 mb-4">
                    <i class="fa-solid fa-file-lines text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Legislative Records</h3>
                <p class="text-sm text-gray-600 leading-relaxed mb-4">
                    Browse and search approved ordinances, resolutions, and other legislative documents.
                </p>
                <div class="flex items-center text-sm font-semibold text-green-600">
                    Browse Records
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                </div>
            </a>
            
            <!-- Committees Card -->
            <a href="<?= BASE_URL ?>/public/portal/committees" 
               class="bg-white rounded-xl p-6 shadow-md hover:shadow-xl transition-all card-hover border border-gray-200">
                <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-purple-100 text-purple-600 mb-4">
                    <i class="fa-solid fa-sitemap text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Committees</h3>
                <p class="text-sm text-gray-600 leading-relaxed mb-4">
                    Learn about the various committees and their areas of oversight and responsibility.
                </p>
                <div class="flex items-center text-sm font-semibold text-purple-600">
                    View Committees
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                </div>
            </a>
            
            <!-- Contact Card -->
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-6 shadow-md text-white">
                <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-white/20 backdrop-blur-sm mb-4">
                    <i class="fa-solid fa-phone text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold mb-2">Contact Us</h3>
                <p class="text-sm text-primary-100 leading-relaxed mb-4">
                    Have questions or need assistance? Get in touch with the provincial office.
                </p>
                <div class="text-sm font-semibold">
                    (075) 542-5000
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- How to Access Section -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-2 lg:gap-12 items-center">
            
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-100 text-primary-700 text-sm font-semibold mb-6">
                    <i class="fa-solid fa-circle-info"></i>
                    For the Public
                </div>
                
                <h2 class="text-3xl font-bold text-gray-900 mb-6">
                    How to Access Legislative Information
                </h2>
                
                <div class="space-y-6">
                    
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 text-primary-600 font-bold">
                                1
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Browse or Search Records</h3>
                            <p class="text-gray-600 leading-relaxed">
                                Use the Legislative Records section to search for specific ordinances, resolutions, 
                                or browse by document type and year.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 text-primary-600 font-bold">
                                2
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">View Document Details</h3>
                            <p class="text-gray-600 leading-relaxed">
                                Click on any record to view its full details, including authors, committees involved, 
                                and available attachments.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 text-primary-600 font-bold">
                                3
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Download Public Documents</h3>
                            <p class="text-gray-600 leading-relaxed">
                                Approved and published documents are available for download. Access official copies 
                                of legislative measures directly from the portal.
                            </p>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <div class="mt-10 lg:mt-0">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-8 border border-primary-200">
                    <div class="mb-6">
                        <i class="fa-solid fa-envelope text-4xl text-primary-600 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Submit a Legislative Concern</h3>
                        <p class="text-gray-700 leading-relaxed">
                            Have a matter you'd like to bring to the attention of the Sangguniang Panlalawigan? 
                            Contact the office to submit a formal communication or request.
                        </p>
                    </div>
                    
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-location-dot text-primary-600 mt-1"></i>
                            <div>
                                <div class="font-semibold text-gray-900">Visit Us:</div>
                                <div class="text-gray-700">Pangasinan Provincial Capitol<br>Lingayen, Pangasinan</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-phone text-primary-600 mt-1"></i>
                            <div>
                                <div class="font-semibold text-gray-900">Call Us:</div>
                                <div class="text-gray-700">(075) 542-5000</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-envelope text-primary-600 mt-1"></i>
                            <div>
                                <div class="font-semibold text-gray-900">Email Us:</div>
                                <div class="text-gray-700">info@pangasinan.gov.ph</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-clock text-primary-600 mt-1"></i>
                            <div>
                                <div class="font-semibold text-gray-900">Office Hours:</div>
                                <div class="text-gray-700">Monday - Friday: 8:00 AM - 5:00 PM</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- About Section -->
<section class="py-16 bg-gray-50 border-t border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-gray-900 mb-6">About the Sangguniang Panlalawigan</h2>
        <p class="text-lg text-gray-600 leading-relaxed mb-6">
            The Sangguniang Panlalawigan is the legislative body of the Province of Pangasinan. 
            It is responsible for enacting ordinances, approving resolutions, and appropriating funds 
            for the general welfare of the province and its inhabitants.
        </p>
        <p class="text-gray-600 leading-relaxed">
            This public portal provides transparency and accessibility to legislative information, 
            allowing citizens to stay informed about the work of their elected representatives and 
            the decisions that shape provincial governance.
        </p>
    </div>
</section>
