<?php

class RoleMiddleware
{
    public function handle(): void
    {
        if (!is_logged_in()) {
            flash_set('error', 'Please sign in to continue.');
            redirect('login');
        }

        $route = $GLOBALS['route'] ?? '';

        $allowed = match (true) {
            str_starts_with($route, 'master') => ['Super Admin'],
            str_starts_with($route, 'receiving') => ['Super Admin', 'Receiving Staff'],
            str_starts_with($route, 'admin') => ['Super Admin', 'Admin'],
            str_starts_with($route, 'spsec') => ['Super Admin', 'SP Secretary'],
            str_starts_with($route, 'plenary') => ['Super Admin', 'Plenary'],
            str_starts_with($route, 'committee') => ['Super Admin', 'Committee'],
            default => null,
        };

        if ($allowed === null) {
            return;
        }

        $role = auth_role();
        if (!in_array($role, $allowed, true)) {
            flash_set('error', 'You do not have permission to access that page.');
            $fallback = dashboard_route_for_role($role);
            redirect($fallback);
        }
    }
}
