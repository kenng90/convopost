<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Membership roles
    |--------------------------------------------------------------------------
    */
    'membership_roles' => [
        'manager' => 'Organization manager',
        'agent' => 'Agent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission levels (Phase 3 fine-grained access)
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'view' => 'View only',
        'manage' => 'Full access',
    ],

    /*
    | Routes that require manage (not view-only) permission for managers.
    */
    'manage_routes' => [
        'agent.create',
        'agent.store',
        'agent.update',
        'agent.delete',
    ],

    /*
    | Modules granted via membership that are not separately plan-gated.
    */
    'plan_exempt_modules' => [
        'agents',
        'managers',
    ],

    /*
    | Routes accessible to all organization members (managers, agents, staff).
    */
    'org_member_routes' => [
        'dashboard',
        'home',
        'profile.show',
        'profile.update',
        'user-profile-information.update',
        'user-password.update',
        'other-browser-sessions.destroy',
        'current-user-photo.destroy',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'sanctum.csrf-cookie',
        'admin.companies.switch',
        'admin.companies.stopImpersonate',
    ],

    /*
    | Routes organization managers can never access (owners only).
    */
    'manager_forbidden_routes' => [
        'agent.loginas',
    ],

    /*
    | Routes blocked for all non-owner organization members.
    */
    'protected_routes' => [
        'plans.current',
        'plans.subscribe',
        'plans.cancel',
        'plans.subscribe_3d_stripe',
        'admin.organizations.manage',
        'admin.organizations.create',
        'admin.companies.create',
        'admin.companies.store',
        'admin.companies.destroy',
        'admin.companies.index',
        'admin.companies.loginas',
        'admin.companies.logout-create',
        'admin.company.remove',
        'admin.share',
        'admin.apps.company',
        'admin.backup.index',
        'admin.backup.download.languages',
        'admin.backup.restore.languages',
        'whatsapp.setup',
        'billing.portal',
    ],

    /*
    | Route prefixes blocked for managers (covers unnamed module routes).
    */
    'protected_route_prefixes' => [
        'plans.',
        'admin.organizations.',
        'admin.backup.',
        'orgmanager.',
    ],

    /*
    | Modules managers can never be granted (account-level).
    */
    'protected_modules' => [
        'pricing',
        'cloner',
    ],

    /*
    | Human-readable labels for grantable modules (fallback to alias).
    */
    'module_labels' => [
        'wpbox' => 'Inbox & WhatsApp',
        'contacts' => 'Contacts',
        'flowmaker' => 'Workflows',
        'campaigns' => 'Campaigns',
        'whatsappflows' => 'WhatsApp forms',
        'whatsappcatalog' => 'Catalog',
        'whatsappcall' => 'WhatsApp calls',
        'reminders' => 'Bookings',
        'knowledge' => 'Knowledge base',
        'reports' => 'Reports',
        'embedwhatsapp' => 'WhatsApp widget',
        'journies' => 'Journeys',
        'agents' => 'Agents',
        'managers' => 'Managers',
    ],

    /*
    | Default manager seat limit per organization (0 = unlimited).
    */
    'manager_seat_limit' => (int) env('ORG_MANAGER_SEAT_LIMIT', 0),

];
