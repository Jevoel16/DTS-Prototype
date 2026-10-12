<?php

namespace App\Middleware;

/**
 * Role-Based Access Control (RBAC) Enforcement Middleware.
 * 
 * Verifies that authenticated users possess appropriate privilege tiers before dispatching protected routes.
 */
class RoleMiddleware
{
    /**
     * Handle the role verification.
     * 
     * Protects routes by ensuring the authenticated user holds one of the required roles.
     * 
     * @param string ...$roles The allowed roles (e.g., 'admin', 'officer', 'staff')
     */
    public function handle(...$roles)
    {
        // Enforce session authentication as prerequisite prior to role verification
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }

        // Verify active role membership against route permissions to prevent unauthorized access across role boundaries
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
            // Immediately abort request execution with HTTP 403 status to prevent unauthorized controller logic execution
            header("HTTP/1.0 403 Forbidden");
            echo "403 Forbidden: You do not have permission to access this page.";
            exit;
        }
    }
}

