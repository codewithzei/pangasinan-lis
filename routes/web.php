<?php

return [

    'home' => [
        'view' => 'home',
        'title' => 'Home',
    ],

    'login' => [
        [
            'method' => 'GET',
            'controller' => 'AuthController',
            'action' => 'showLogin',
            'middleware' => ['GuestMiddleware'],
        ],
        [
            'method' => 'POST',
            'controller' => 'AuthController',
            'action' => 'login',
            'middleware' => ['GuestMiddleware'],
        ],
    ],

    'register' => [
        [
            'method' => 'GET',
            'controller' => 'AuthController',
            'action' => 'showRegister',
            'middleware' => ['GuestMiddleware'],
        ],
        [
            'method' => 'POST',
            'controller' => 'AuthController',
            'action' => 'register',
            'middleware' => ['GuestMiddleware'],
        ],
    ],

    'logout' => [
        'method' => 'POST',
        'controller' => 'AuthController',
        'action' => 'logout',
        'middleware' => ['AuthMiddleware'],
    ],

    'dashboard' => [
        'controller' => 'AuthController',
        'action' => 'relogin',
        'middleware' => ['AuthMiddleware'],
    ],

    'master/dashboard' => [
        'controller' => 'Admin/DashboardController',
        'action' => 'index',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'receiving/dashboard' => [
        'view' => 'receiving/dashboard',
        'title' => 'Receiving Dashboard',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'admin/dashboard' => [
        'view' => 'admin/dashboard',
        'title' => 'Admin / Routing Dashboard',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'spsec/dashboard' => [
        'view' => 'spsec/dashboard',
        'title' => 'SP Secretary Dashboard',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'plenary/dashboard' => [
        'view' => 'plenary/dashboard',
        'title' => 'Plenary Dashboard',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'committee/dashboard' => [
        'view' => 'committee/dashboard',
        'title' => 'Committee Dashboard',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'profile' => [
        [
            'method' => 'GET',
            'controller' => 'User/ProfileController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware'],
        ],
    ],

    'profile/update' => [
        'method' => 'POST',
        'controller' => 'User/ProfileController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware'],
    ],

    'test/database' => [
        'controller' => 'TestController',
        'action' => 'database',
        'title' => 'Database Test',
    ],

    'master/legislative-terms' => [
        [
            'method' => 'GET',
            'controller' => 'Master/TermController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/legislative-terms/store' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/edit' => [
        'method' => 'GET',
        'controller' => 'Master/TermController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/update' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/set-active' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'setActive',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/clone' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'clone',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/show' => [
        'method' => 'GET',
        'controller' => 'Master/TermController',
        'action' => 'show',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/assign-legislators' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'assignLegislators',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/remove-legislator' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'removeLegislator',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/export-csv' => [
        'method' => 'GET',
        'controller' => 'Master/TermController',
        'action' => 'exportCsv',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/legislative-terms/generate-name' => [
        'method' => 'GET',
        'controller' => 'Master/TermController',
        'action' => 'generateNameSuggestion',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/positions' => [
        [
            'method' => 'GET',
            'controller' => 'Master/PositionController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/positions/store' => [
        'method' => 'POST',
        'controller' => 'Master/PositionController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/positions/edit' => [
        'method' => 'GET',
        'controller' => 'Master/PositionController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/positions/update' => [
        'method' => 'POST',
        'controller' => 'Master/PositionController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/positions/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/PositionController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/positions/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/PositionController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/districts' => [
        [
            'method' => 'GET',
            'controller' => 'Master/DistrictController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/districts/store' => [
        'method' => 'POST',
        'controller' => 'Master/DistrictController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/districts/edit' => [
        'method' => 'GET',
        'controller' => 'Master/DistrictController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/districts/update' => [
        'method' => 'POST',
        'controller' => 'Master/DistrictController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/districts/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/DistrictController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/districts/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/DistrictController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/municipalities' => [
        [
            'method' => 'GET',
            'controller' => 'Master/MuniCityController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/municipalities/store' => [
        'method' => 'POST',
        'controller' => 'Master/MuniCityController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/municipalities/edit' => [
        'method' => 'GET',
        'controller' => 'Master/MuniCityController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/municipalities/update' => [
        'method' => 'POST',
        'controller' => 'Master/MuniCityController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/municipalities/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/MuniCityController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/municipalities/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/MuniCityController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-types' => [
        [
            'method' => 'GET',
            'controller' => 'Master/DocumentTypeController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/document-types/store' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentTypeController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-types/edit' => [
        'method' => 'GET',
        'controller' => 'Master/DocumentTypeController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-types/update' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentTypeController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-types/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentTypeController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-types/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentTypeController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-statuses' => [
        [
            'method' => 'GET',
            'controller' => 'Master/DocumentStatusController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/document-statuses/store' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentStatusController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-statuses/edit' => [
        'method' => 'GET',
        'controller' => 'Master/DocumentStatusController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-statuses/update' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentStatusController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-statuses/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentStatusController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/document-statuses/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/DocumentStatusController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/source-types' => [
        [
            'method' => 'GET',
            'controller' => 'Master/SourceTypeController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/source-types/store' => [
        'method' => 'POST',
        'controller' => 'Master/SourceTypeController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/source-types/edit' => [
        'method' => 'GET',
        'controller' => 'Master/SourceTypeController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/source-types/update' => [
        'method' => 'POST',
        'controller' => 'Master/SourceTypeController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/source-types/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/SourceTypeController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/source-types/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/SourceTypeController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/external-offices' => [
        [
            'method' => 'GET',
            'controller' => 'Master/ExternalOfficeController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/external-offices/store' => [
        'method' => 'POST',
        'controller' => 'Master/ExternalOfficeController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/external-offices/edit' => [
        'method' => 'GET',
        'controller' => 'Master/ExternalOfficeController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/external-offices/update' => [
        'method' => 'POST',
        'controller' => 'Master/ExternalOfficeController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/external-offices/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/ExternalOfficeController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/external-offices/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/ExternalOfficeController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/routing-options' => [
        [
            'method' => 'GET',
            'controller' => 'Master/RoutingOptionController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/routing-options/store' => [
        'method' => 'POST',
        'controller' => 'Master/RoutingOptionController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/routing-options/edit' => [
        'method' => 'GET',
        'controller' => 'Master/RoutingOptionController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/routing-options/update' => [
        'method' => 'POST',
        'controller' => 'Master/RoutingOptionController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/routing-options/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/RoutingOptionController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/routing-options/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/RoutingOptionController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/communication-categories' => [
        [
            'method' => 'GET',
            'controller' => 'Master/CommunicationCategoryController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/communication-categories/store' => [
        'method' => 'POST',
        'controller' => 'Master/CommunicationCategoryController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/communication-categories/edit' => [
        'method' => 'GET',
        'controller' => 'Master/CommunicationCategoryController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/communication-categories/update' => [
        'method' => 'POST',
        'controller' => 'Master/CommunicationCategoryController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/communication-categories/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/CommunicationCategoryController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/communication-categories/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/CommunicationCategoryController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-offices' => [
        [
            'method' => 'GET',
            'controller' => 'Master/OpinionOfficeController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/opinion-offices/store' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionOfficeController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-offices/edit' => [
        'method' => 'GET',
        'controller' => 'Master/OpinionOfficeController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-offices/update' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionOfficeController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-offices/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionOfficeController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-offices/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionOfficeController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-statuses' => [
        [
            'method' => 'GET',
            'controller' => 'Master/OpinionStatusController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/opinion-statuses/store' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionStatusController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-statuses/edit' => [
        'method' => 'GET',
        'controller' => 'Master/OpinionStatusController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-statuses/update' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionStatusController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-statuses/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionStatusController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/opinion-statuses/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/OpinionStatusController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/hospitals' => [
        [
            'method' => 'GET',
            'controller' => 'Master/HospitalController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/hospitals/store' => [
        'method' => 'POST',
        'controller' => 'Master/HospitalController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/hospitals/edit' => [
        'method' => 'GET',
        'controller' => 'Master/HospitalController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/hospitals/update' => [
        'method' => 'POST',
        'controller' => 'Master/HospitalController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/hospitals/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/HospitalController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/hospitals/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/HospitalController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/system-logs' => [
        'method' => 'GET',
        'controller' => 'Master/SystemLogController',
        'action' => 'index',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/system-logs/show' => [
        'method' => 'GET',
        'controller' => 'Master/SystemLogController',
        'action' => 'show',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/system-logs/export-csv' => [
        'method' => 'GET',
        'controller' => 'Master/SystemLogController',
        'action' => 'exportCsv',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/system-logs/clear-old' => [
        'method' => 'POST',
        'controller' => 'Master/SystemLogController',
        'action' => 'clearOld',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/audit-logs' => [
        'method' => 'GET',
        'controller' => 'Master/AuditLogController',
        'action' => 'index',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/audit-logs/show' => [
        'method' => 'GET',
        'controller' => 'Master/AuditLogController',
        'action' => 'show',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/audit-logs/export-csv' => [
        'method' => 'GET',
        'controller' => 'Master/AuditLogController',
        'action' => 'exportCsv',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/audit-logs/clear-old' => [
        'method' => 'POST',
        'controller' => 'Master/AuditLogController',
        'action' => 'clearOld',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/sp-members' => [
        [
            'method' => 'GET',
            'controller' => 'Master/SpMemberController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/sp-members/store' => [
        'method' => 'POST',
        'controller' => 'Master/SpMemberController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/sp-members/edit' => [
        'method' => 'GET',
        'controller' => 'Master/SpMemberController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/sp-members/update' => [
        'method' => 'POST',
        'controller' => 'Master/SpMemberController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/sp-members/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/SpMemberController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/sp-members/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/SpMemberController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists' => [
        [
            'method' => 'GET',
            'controller' => 'Master/ChecklistController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/checklists/store' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/edit' => [
        'method' => 'GET',
        'controller' => 'Master/ChecklistController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/update' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/show' => [
        'method' => 'GET',
        'controller' => 'Master/ChecklistController',
        'action' => 'show',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/assign-document-types' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistDocumentTypeController',
        'action' => 'assign',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/remove-document-type' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistDocumentTypeController',
        'action' => 'remove',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/checklists/update-required' => [
        'method' => 'POST',
        'controller' => 'Master/ChecklistDocumentTypeController',
        'action' => 'updateRequired',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/user-roles' => [
        [
            'method' => 'GET',
            'controller' => 'Master/UserRoleController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/user-roles/store' => [
        'method' => 'POST',
        'controller' => 'Master/UserRoleController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/user-roles/edit' => [
        'method' => 'GET',
        'controller' => 'Master/UserRoleController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/user-roles/update' => [
        'method' => 'POST',
        'controller' => 'Master/UserRoleController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/user-roles/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/UserRoleController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/user-roles/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/UserRoleController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/committees' => [
        [
            'method' => 'GET',
            'controller' => 'Master/CommitteeController',
            'action' => 'index',
            'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
        ],
    ],

    'master/committees/store' => [
        'method' => 'POST',
        'controller' => 'Master/CommitteeController',
        'action' => 'store',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/committees/edit' => [
        'method' => 'GET',
        'controller' => 'Master/CommitteeController',
        'action' => 'edit',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/committees/update' => [
        'method' => 'POST',
        'controller' => 'Master/CommitteeController',
        'action' => 'update',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/committees/destroy' => [
        'method' => 'POST',
        'controller' => 'Master/CommitteeController',
        'action' => 'destroy',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

    'master/committees/toggle-status' => [
        'method' => 'POST',
        'controller' => 'Master/CommitteeController',
        'action' => 'toggleStatus',
        'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
    ],

];
