<?php

class AuthMiddleware
{
    public function handle(): void
    {
        if (!is_logged_in()) {
            flash_set('error', 'Please sign in to continue.');
            redirect('login');
        }

        $user = auth();
        if (!empty($user['status']) && $user['status'] !== 'active') {
            session_destroy();
            flash_set('error', 'Your account is not active. Please contact the administrator.');
            redirect('login');
        }
    }
}
