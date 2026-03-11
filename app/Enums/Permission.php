<?php

namespace App\Enums;

class Permission
{
    // Posts
    const POSTS_VIEW = 'posts.view';
    const POSTS_CREATE = 'posts.create';
    const POSTS_EDIT = 'posts.edit';
    const POSTS_DELETE = 'posts.delete';

    // Pages
    const PAGES_VIEW = 'pages.view';
    const PAGES_CREATE = 'pages.create';
    const PAGES_EDIT = 'pages.edit';
    const PAGES_DELETE = 'pages.delete';

    // Media
    const MEDIA_VIEW = 'media.view';
    const MEDIA_UPLOAD = 'media.upload';
    const MEDIA_DELETE = 'media.delete';

    // Users
    const USERS_VIEW = 'users.view';
    const USERS_CREATE = 'users.create';
    const USERS_EDIT = 'users.edit';
    const USERS_DELETE = 'users.delete';

    // Roles
    const ROLES_VIEW = 'roles.view';
    const ROLES_CREATE = 'roles.create';
    const ROLES_EDIT = 'roles.edit';
    const ROLES_DELETE = 'roles.delete';

    // Settings
    const SETTINGS_VIEW = 'settings.view';
    const SETTINGS_EDIT = 'settings.edit';

    // Menus
    const MENUS_VIEW = 'menus.view';
    const MENUS_EDIT = 'menus.edit';

    // Audit
    const AUDIT_READ = 'audit.read';

    public static function all(): array
    {
        return [
            self::POSTS_VIEW, self::POSTS_CREATE, self::POSTS_EDIT, self::POSTS_DELETE,
            self::PAGES_VIEW, self::PAGES_CREATE, self::PAGES_EDIT, self::PAGES_DELETE,
            self::MEDIA_VIEW, self::MEDIA_UPLOAD, self::MEDIA_DELETE,
            self::USERS_VIEW, self::USERS_CREATE, self::USERS_EDIT, self::USERS_DELETE,
            self::ROLES_VIEW, self::ROLES_CREATE, self::ROLES_EDIT, self::ROLES_DELETE,
            self::SETTINGS_VIEW, self::SETTINGS_EDIT,
            self::MENUS_VIEW, self::MENUS_EDIT,
            self::AUDIT_READ,
        ];
    }
}
