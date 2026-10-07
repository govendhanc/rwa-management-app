<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Navbar notification bell (AJAX). Every logged-in user may read their own notifications.
 */
class Notifications extends Auth_Controller
{
    public function latest(): void
    {
        $this->require_ajax();

        $items = array();
        foreach ($this->notification_model->latest((int) user_id(), 10) as $row)
        {
            $items[] = array(
                'id'               => (int) $row['id'],
                'title'            => $row['title'],
                'message'          => $row['message'],
                'link'             => $row['link'],
                'is_read'          => (int) $row['is_read'] === 1,
                'created_at_label' => fmt_datetime($row['created_at']),
            );
        }
        $this->json_success('OK', array('items' => $items, 'unread' => $this->notification_model->unread_count((int) user_id())));
    }

    public function mark_read(): void
    {
        $this->require_ajax();
        $this->require_post();
        $this->notification_model->mark_all_read((int) user_id());
        $this->json_success('Notifications marked as read.');
    }
}
