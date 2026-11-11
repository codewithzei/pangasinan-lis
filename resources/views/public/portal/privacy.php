<!-- Hero Section -->
<section class="portal-hero text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 text-blue-100 mb-3">
            <a href="<?= BASE_URL ?>/public/portal" class="hover:text-white transition-colors">
                <i class="fa-solid fa-home"></i> Portal
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <span class="text-white font-medium">Privacy Policy</span>
        </div>
        
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
            Privacy Policy
        </h1>
        <p class="text-xl text-blue-100 max-w-3xl">
            Important information about personal data and this public-records portal
        </p>
    </div>
</section>

<!-- Notice Banner -->
<section class="bg-amber-50 border-b border-amber-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-circle-info text-amber-600 text-xl mt-1"></i>
            <div>
                <p class="text-sm font-semibold text-gray-900 mb-1">Notice</p>
                <p class="text-sm text-gray-700">
                    This privacy policy is provided as a placeholder and must be reviewed and approved by the 
                    Provincial Legal Office and Data Protection Officer before being considered official.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<section class="py-16 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="prose prose-lg max-w-none">
            
            <p class="text-gray-600 text-sm mb-8">
                <strong>Status:</strong> Draft for review — not an approved privacy policy.
            </p>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Introduction</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    This portal provides public-facing information about the Sangguniang Panlalawigan and selected
                    archived legislative records. This page is not an approved statement of the Provincial Government's
                    data-processing practices; it must be reviewed by the responsible privacy and legal offices before
                    being relied on as formal policy.
                </p>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Public information and confidential records</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    Only archived records explicitly published for public access are included in the public record listing.
                    Internal workflow documents and case materials are not presented as public records through this portal.
                    Do not treat a record's publication as confirmation that a newly added attachment is suitable for
                    disclosure; attachments require review before the record is published.
                </p>
                <p class="text-gray-700 leading-relaxed mb-4">
                    Please avoid sending sensitive personal information through general contact channels. For requests
                    about personal data, records, or official privacy procedures, use contact details confirmed by the
                    Provincial Government.
                </p>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Policy review</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    The responsible offices should verify the portal's actual data collection, logging, cookies,
                    retention, security, and request-handling practices before replacing this draft with an official
                    policy. No unverified practice or contact channel is asserted on this page.
                </p>
            </div>

            <div class="mb-12 bg-primary-50 rounded-xl p-8 border border-primary-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Questions</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    For general inquiries, use the portal's contact page. Verify any contact channel before sharing
                    personal or confidential information.
                </p>
                <a href="<?= BASE_URL ?>/public/portal/contact"
                   class="inline-flex items-center gap-2 font-semibold text-primary-700 hover:text-primary-900">
                    View contact information <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

        </div>

    </div>
</section>

<!-- Call to Action -->
<section class="py-12 bg-gray-50 border-t border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Have Questions?</h2>
        <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
            For official privacy procedures, contact the responsible provincial office through a verified government channel.
        </p>
        <a href="<?= BASE_URL ?>/public/portal/contact" 
           class="inline-flex items-center gap-2 px-8 py-3 bg-primary text-white font-semibold rounded-xl hover:bg-primary/90 transition-colors shadow-md">
            <i class="fa-solid fa-envelope"></i>
            Contact Us
        </a>
    </div>
</section>
