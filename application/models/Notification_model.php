<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * In-app notifications (navbar bell).
 * Notifications for "everyone" are fanned out to one row per active user so
 * each user has their own read/unread state.
 */
class Notification_model extends MY_Model
{
    protected $table = 'notifications';

    public function unread_count(int $user_id): int
    {
        return (int) $this->db->from('notifications')
            ->where('user_id', $user_id)
            ->where('is_read', 0)
            ->count_all_results();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $user_id, int $limit = 10): array
    {
        return $this->db->from('notifications')
            ->where('user_id', $user_id)
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->get()->result_array();
    }

    public function mark_all_read(int $user_id): void
    {
        $this->db->where('user_id', $user_id)->where('is_read', 0)
            ->update('notifications', array('is_read' => 1, 'read_at' => date('Y-m-d H:i:s')));
    }

    /**
     * Create one notification for every active user who holds $permission (NULL = all active users).
     */
    public function notify_users(string $type, string $title, string $message, string $link, ?string $permission = NULL): void
    {
        $this->db->select('u.id')->from('users u')->where('u.status', 'Active');
        if ($permission !== NULL)
        {
            $this->db->join('roles r', 'r.id = u.role_id')
                ->group_start()
                    ->where('r.role_key', 'super_admin')
                    ->or_where('u.role_id IN (SELECT rp.role_id FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.perm_key = '.$this->db->escape($permission).')', NULL, FALSE)
                ->group_end();
        }
        $rows = array();
        foreach ($this->db->get()->result_array() as $user)
        {
            $rows[] = array(
                'user_id' => (int) $user['id'],
                'type'    => mb_substr($type, 0, 30),
                'title'   => mb_substr($title, 0, 150),
                'message' => mb_substr($message, 0, 500),
                'link'    => mb_substr($link, 0, 255),
            );
        }
        if ( ! empty($rows))
        {
            $this->db->insert_batch('notifications', $rows);
        }
    }
}
