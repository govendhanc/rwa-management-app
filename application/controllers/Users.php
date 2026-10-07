<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User & role management (permission: users.manage).
 *
 * Safety rules:
 *  - Only a Super Admin can create, edit or assign the Super Admin role.
 *  - Users cannot deactivate themselves or change their own role.
 *  - The last active Super Admin can never be deactivated or demoted.
 *  - Users are never deleted (audit trail); they are deactivated instead.
 */
class Users extends Auth_Controller
{
    protected $permission_map = array(
        'index'          => 'users.manage',
        'create'         => 'users.manage',
        'edit'           => 'users.manage',
        'toggle_status'  => 'users.manage',
        'reset_password' => 'users.manage',
        'unlock'         => 'users.manage',
        'roles'          => 'users.manage',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('user_model', 'role_model'));
        $this->load->library('form_validation');
    }

    public function index(): void
    {
        $this->render('users/index', array(
            'page_title'  => 'Users',
            'breadcrumbs' => array(array('label' => 'Users')),
            'users'       => $this->user_model->list_all(),
            'scripts'     => array('js/modules/users.js'),
        ));
    }

    public function create(): void
    {
        $user = array(
            'id' => NULL, 'full_name' => '', 'username' => '', 'email' => '', 'mobile' => '',
            'role_id' => '', 'status' => 'Active', 'must_change_password' => 1,
        );

        if ($this->input->method() === 'post')
        {
            $this->set_user_rules(NULL);
            if ($this->form_validation->run())
            {
                $data = $this->collect_user_input();
                if ( ! $this->can_assign_role((int) $data['role_id']))
                {
                    $this->deny('assign Super Admin role');
                }
                $data['password_hash'] = $this->auth_lib->hash_password((string) $this->input->post('password'));
                $data['must_change_password'] = $this->input->post('must_change_password') === '1' ? 1 : 0;
                $data['created_by'] = user_id();
                $data['updated_by'] = user_id();

                $id = $this->user_model->insert($data);
                if ($id > 0)
                {
                    $this->audit->log('User Created', 'users', $id, NULL, $data);
                    $this->session->set_flashdata('success', 'User "'.$data['username'].'" created.');
                    redirect('users');
                }
                $this->session->set_flashdata('error', 'The user could not be saved. Please try again.');
            }
        }

        $this->render_form($user, 'Add User');
    }

    public function edit(int $id = 0): void
    {
        $user = $this->user_model->find_with_role($id);
        if ($user === NULL)
        {
            show_404();
        }
        if ($user['role_key'] === 'super_admin' && ! is_super_admin())
        {
            $this->deny('edit Super Admin');
        }

        if ($this->input->method() === 'post')
        {
            $this->set_user_rules($id);
            if ($this->form_validation->run())
            {
                $data = $this->collect_user_input();
                $error = $this->check_role_and_status_change($user, (int) $data['role_id'], $data['status']);
                if ($error !== NULL)
                {
                    $this->session->set_flashdata('error', $error);
                    redirect('users/edit/'.$id);
                }

                $password = (string) $this->input->post('password');
                if ($password !== '')
                {
                    $data['password_hash'] = $this->auth_lib->hash_password($password);
                    $data['password_changed_at'] = date('Y-m-d H:i:s');
                    $data['must_change_password'] = $this->input->post('must_change_password') === '1' ? 1 : 0;
                }
                $data['updated_by'] = user_id();

                $this->user_model->update($id, $data);
                if ($password !== '' || $data['status'] !== 'Active')
                {
                    $this->user_model->delete_all_remember_tokens($id);
                }
                $this->audit->log('User Updated', 'users', $id, $user, $data + ($password !== '' ? array('password_changed' => TRUE) : array()));
                $this->session->set_flashdata('success', 'User "'.$data['username'].'" updated.');
                redirect('users');
            }
        }

        $this->render_form($user, 'Edit User');
    }

    /**
     * AJAX: activate / deactivate a user.
     */
    public function toggle_status(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();

        $user = $this->user_model->find_with_role($id);
        if ($user === NULL)
        {
            $this->json_error('User not found.', 404);
        }
        $new_status = $user['status'] === 'Active' ? 'Inactive' : 'Active';
        $error = $this->check_role_and_status_change($user, (int) $user['role_id'], $new_status);
        if ($error !== NULL)
        {
            $this->json_error($error);
        }
        if ($user['role_key'] === 'super_admin' && ! is_super_admin())
        {
            $this->deny('change Super Admin');
        }

        $this->user_model->update($id, array('status' => $new_status, 'updated_by' => user_id()));
        if ($new_status === 'Inactive')
        {
            $this->user_model->delete_all_remember_tokens($id);
        }
        $this->audit->log('User Status Changed', 'users', $id, array('status' => $user['status']), array('status' => $new_status));
        $this->json_success('User "'.$user['username'].'" is now '.$new_status.'.', array('status' => $new_status));
    }

    /**
     * AJAX: generate a temporary password; the user must change it at next login.
     */
    public function reset_password(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();

        $user = $this->user_model->find_with_role($id);
        if ($user === NULL)
        {
            $this->json_error('User not found.', 404);
        }
        if ($user['role_key'] === 'super_admin' && ! is_super_admin())
        {
            $this->deny('reset Super Admin password');
        }
        if ((int) $user['id'] === user_id())
        {
            $this->json_error('Use "Change Password" to change your own password.');
        }

        $temp = $this->temporary_password();
        $this->user_model->update($id, array(
            'password_hash'        => $this->auth_lib->hash_password($temp),
            'must_change_password' => 1,
            'password_changed_at'  => date('Y-m-d H:i:s'),
            'failed_attempts'      => 0,
            'locked_until'         => NULL,
            'updated_by'           => user_id(),
        ));
        $this->user_model->delete_all_remember_tokens($id);
        $this->audit->log('User Password Reset', 'users', $id, NULL, array('must_change_password' => 1));

        $this->json_success('Temporary password generated for "'.$user['username'].'".', array('temporary_password' => $temp));
    }

    /**
     * AJAX: clear a login lockout.
     */
    public function unlock(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();

        $user = $this->user_model->find($id);
        if ($user === NULL)
        {
            $this->json_error('User not found.', 404);
        }
        $this->user_model->update($id, array('failed_attempts' => 0, 'locked_until' => NULL, 'updated_by' => user_id()));
        $this->db->where('is_success', 0)
            ->group_start()
                ->where('login_identifier', $user['username'])
                ->or_where('login_identifier', $user['email'])
            ->group_end()
            ->delete('login_attempts');
        $this->audit->log('User Unlocked', 'users', $id);
        $this->json_success('User "'.$user['username'].'" has been unlocked.');
    }

    /**
     * Roles & permission matrix. Super Admin always holds every permission.
     */
    public function roles(): void
    {
        if ( ! is_super_admin())
        {
            $this->deny('edit role permissions');
        }

        $roles = $this->role_model->all_roles();

        if ($this->input->method() === 'post')
        {
            $posted = $this->input->post('grants');
            $posted = is_array($posted) ? $posted : array();
            $valid_ids = $this->role_model->valid_permission_ids();
            $before = $this->role_model->grant_map();

            $this->db->trans_start();
            foreach ($roles as $role)
            {
                if ($role['role_key'] === 'super_admin')
                {
                    continue;
                }
                $ids = isset($posted[$role['id']]) && is_array($posted[$role['id']]) ? $posted[$role['id']] : array();
                $ids = array_values(array_intersect(array_map('intval', $ids), $valid_ids));
                $this->role_model->replace_grants((int) $role['id'], $ids);
            }
            $this->db->trans_complete();

            if ($this->db->trans_status())
            {
                $this->audit->log('Role Permissions Updated', 'users', 'roles', array('grants' => $before), array('grants' => $this->role_model->grant_map()));
                $this->session->set_flashdata('success', 'Role permissions saved. Changes apply on each user\'s next page load.');
            }
            else
            {
                $this->session->set_flashdata('error', 'Role permissions could not be saved.');
            }
            redirect('users/roles');
        }

        $this->render('users/roles', array(
            'page_title'  => 'Roles & Permissions',
            'breadcrumbs' => array(array('label' => 'Users', 'url' => 'users'), array('label' => 'Roles & Permissions')),
            'roles'       => $roles,
            'modules'     => $this->role_model->permissions_by_module(),
            'grants'      => $this->role_model->grant_map(),
        ));
    }

    /* ------------------------------------------------------------------ */

    private function set_user_rules(?int $id): void
    {
        $ignore = $id !== NULL ? (string) $id : '';
        $this->form_validation->set_rules('full_name', 'Name', 'trim|required|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('username', 'Username', 'trim|required|min_length[3]|max_length[50]|alpha_dash|unique_except[users.username.'.$ignore.']');
        $this->form_validation->set_rules('email', 'E-mail', 'trim|required|valid_email|max_length[150]|unique_except[users.email.'.$ignore.']');
        $this->form_validation->set_rules('mobile', 'Mobile', 'trim|valid_mobile');
        $this->form_validation->set_rules('role_id', 'Role', 'required|is_natural_no_zero|exists_in[roles.id]');
        $this->form_validation->set_rules('status', 'Status', 'required|in_list[Active,Inactive]');

        $password_rules = $id === NULL ? 'required|strong_password' : 'strong_password';
        $this->form_validation->set_rules('password', 'Password', $password_rules);
        $confirm_rules = ($id === NULL || (string) $this->input->post('password') !== '') ? 'required|matches[password]' : '';
        $this->form_validation->set_rules('password_confirm', 'Confirm Password', $confirm_rules, array('matches' => 'The passwords do not match.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_user_input(): array
    {
        $mobile = normalize_mobile((string) $this->input->post('mobile'));
        return array(
            'full_name' => (string) $this->input->post('full_name', TRUE),
            'username'  => strtolower((string) $this->input->post('username', TRUE)),
            'email'     => strtolower((string) $this->input->post('email', TRUE)),
            'mobile'    => $mobile !== '' ? $mobile : NULL,
            'role_id'   => (int) $this->input->post('role_id'),
            'status'    => (string) $this->input->post('status'),
        );
    }

    private function can_assign_role(int $role_id): bool
    {
        $role = $this->role_model->find($role_id);
        return $role !== NULL && ($role['role_key'] !== 'super_admin' || is_super_admin());
    }

    /**
     * @param array<string, mixed> $user Current row with role_key
     */
    private function check_role_and_status_change(array $user, int $new_role_id, string $new_status): ?string
    {
        $is_self = (int) $user['id'] === user_id();
        if ($is_self && $new_status !== 'Active')
        {
            return 'You cannot deactivate your own account.';
        }
        if ($is_self && $new_role_id !== (int) $user['role_id'])
        {
            return 'You cannot change your own role.';
        }
        if ( ! $this->can_assign_role($new_role_id))
        {
            return 'Only a Super Admin can assign the Super Admin role.';
        }

        $new_role = $this->role_model->find($new_role_id);
        $leaves_super_admin = $user['role_key'] === 'super_admin'
            && $user['status'] === 'Active'
            && ($new_status !== 'Active' || $new_role === NULL || $new_role['role_key'] !== 'super_admin');
        if ($leaves_super_admin && $this->user_model->count_active_super_admins((int) $user['id']) === 0)
        {
            return 'This is the only active Super Admin. Create another Super Admin first.';
        }
        return NULL;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function render_form(array $user, string $title): void
    {
        $roles = $this->role_model->options();
        if ( ! is_super_admin())
        {
            foreach ($this->role_model->all_roles() as $role)
            {
                if ($role['role_key'] === 'super_admin')
                {
                    unset($roles[(int) $role['id']]);
                }
            }
        }

        $this->render('users/form', array(
            'page_title'  => $title,
            'breadcrumbs' => array(array('label' => 'Users', 'url' => 'users'), array('label' => $title)),
            'user'        => $user,
            'roles'       => $roles,
            'is_self'     => $user['id'] !== NULL && (int) $user['id'] === user_id(),
        ));
    }

    private function temporary_password(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $password = '';
        for ($i = 0; $i < 7; $i++)
        {
            $password .= $letters[random_int(0, strlen($letters) - 1)];
        }
        for ($i = 0; $i < 3; $i++)
        {
            $password .= $digits[random_int(0, strlen($digits) - 1)];
        }
        return str_shuffle($password);
    }
}
