<?php

class GuestMiddleware
{
    public function handle(): void
    {
        if (is_logged_in()) {
            $redirect = dashboard_route_for_role(auth_role());
            redirect($redirect);
        }
    }
}
