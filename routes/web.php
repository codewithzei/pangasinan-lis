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

    'master/legislative-terms/update-legislator-role' => [
        'method' => 'POST',
        'controller' => 'Master/TermController',
        'action' => 'updateLegislatorRole',
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

];
