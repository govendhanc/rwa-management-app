<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ROWA Portal - permission helpers.
 *
 * The logged-in user and the user's permission keys are stored in the session
 * at login (see Auth_lib). These helpers only read the session, so they are
 * cheap enough to call in views (menu, buttons).
 *
 * Authorization is ALWAYS enforced again in controllers; hiding a button is
 * never treated as security.
 */

if ( ! function_exists('current_user'))
{
    /**
     * @return array<string, mixed>|null
     */
    function current_user(): ?array
    {
        $CI =& get_instance();
        $user = $CI->session->userdata('auth_user');
        return is_array($user) ? $user : NULL;
    }
}

if ( ! function_exists('is_logged_in'))
{
    function is_logged_in(): bool
    {
        return current_user() !== NULL;
    }
}

if ( ! function_exists('user_id'))
{
    function user_id(): ?int
    {
        $user = current_user();
        return $user !== NULL ? (int) $user['id'] : NULL;
    }
}

if ( ! function_exists('is_super_admin'))
{
    function is_super_admin(): bool
    {
        $user = current_user();
        return $user !== NULL && $user['role_key'] === 'super_admin';
    }
}

if ( ! function_exists('can'))
{
    /**
     * Does the logged-in user hold the permission? Super Admin holds all.
     */
    function can(string $permission): bool
    {
        if ( ! is_logged_in())
        {
            return FALSE;
        }
        if (is_super_admin())
        {
            return TRUE;
        }
        $CI =& get_instance();
        $permissions = $CI->session->userdata('auth_permissions');
        return is_array($permissions) && in_array($permission, $permissions, TRUE);
    }
}

if ( ! function_exists('can_any'))
{
    /**
     * @param string[] $permissions
     */
    function can_any(array $permissions): bool
    {
        foreach ($permissions as $permission)
        {
            if (can($permission))
            {
                return TRUE;
            }
        }
        return FALSE;
    }
}

if ( ! function_exists('visible_menu'))
{
    /**
     * Filter the sidebar menu definition by permission.
     *
     * @param array<int, array<string, mixed>> $items
     * @param bool $show_all  Development preview only: show every item.
     * @return array<int, array<string, mixed>>
     */
    function visible_menu(array $items, bool $show_all = FALSE): array
    {
        $visible = array();
        foreach ($items as $item)
        {
            if (isset($item['section']))
            {
                $visible[] = $item;
                continue;
            }

            if (isset($item['children']))
            {
                $item['children'] = visible_menu($item['children'], $show_all);
                if (empty($item['children']))
                {
                    continue;
                }
                $visible[] = $item;
                continue;
            }

            $perm = $item['perm'] ?? NULL;
            if ($show_all || $perm === NULL || can($perm))
            {
                $visible[] = $item;
            }
        }

        // Drop section headings that have no items under them
        $result = array();
        $count = count($visible);
        for ($i = 0; $i < $count; $i++)
        {
            if (isset($visible[$i]['section']) && ( ! isset($visible[$i + 1]) || isset($visible[$i + 1]['section'])))
            {
                continue;
            }
            $result[] = $visible[$i];
        }
        return $result;
    }
}
