<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant move-in / update / move-out, keeping house occupancy in sync.
 * Every method runs in a transaction and returns array{success, message, id?}.
 */
class Tenant_service
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('tenant_model', 'house_model', 'sequence_model'));
        $this->CI->load->library('audit');
    }

    /**
     * Record a new tenant moving into a house.
     *
     * @param array<string, mixed> $tenant Tenant columns (house_id required)
     * @return array<string, mixed>
     */
    public function move_in(array $tenant): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $house = $this->CI->house_model->find_for_update((int) $tenant['house_id']);
            if ($house === NULL || $house['status'] !== 'Active')
            {
                throw new DomainException('The selected house is not active.');
            }
            if ($house['owner_id'] === NULL)
            {
                throw new DomainException('Plot '.$house['plot_no'].' has no owner. Assign an owner before adding a tenant.');
            }
            if ($this->CI->house_model->active_tenant((int) $house['id']) !== NULL)
            {
                throw new DomainException('Plot '.$house['plot_no'].' already has an active tenant. Record that tenant\'s move-out first.');
            }

            $tenant['owner_id'] = (int) $house['owner_id'];
            $tenant['status'] = 'Active';
            $tenant['tenant_code'] = $this->CI->sequence_model->formatted('TENANT', 'TEN', 5);
            $tenant['created_by'] = user_id();
            $tenant['updated_by'] = user_id();
            $id = $this->CI->tenant_model->insert($tenant);
            if ($id === 0)
            {
                throw new RuntimeException('The tenant could not be saved.');
            }

            $this->CI->house_model->update((int) $house['id'], array('occupancy_status' => 'Tenant Occupied', 'updated_by' => user_id()));
            $this->CI->audit->log('Tenant Moved In', 'tenants', $id, NULL, $tenant + array('plot_no' => $house['plot_no']));

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The tenant could not be saved.');
            }
            $db->trans_commit();
            return array('success' => TRUE, 'message' => 'Tenant '.$tenant['tenant_code'].' - '.$tenant['tenant_name'].' added to Plot '.$house['plot_no'].'. The house is now Tenant Occupied.', 'id' => $id);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'move in');
        }
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(array $old, array $data): array
    {
        $data['updated_by'] = user_id();
        if ( ! $this->CI->tenant_model->update((int) $old['id'], $data))
        {
            return array('success' => FALSE, 'message' => 'The tenant could not be updated.');
        }
        $this->CI->audit->log('Tenant Updated', 'tenants', $old['id'], $old, $data);
        return array('success' => TRUE, 'message' => 'Tenant '.$old['tenant_code'].' updated.');
    }

    /**
     * Record a move-out and set the house occupancy afterwards ('Owner Occupied' or 'Vacant').
     *
     * @return array<string, mixed>
     */
    public function move_out(int $tenant_id, string $move_out_date, string $occupancy_after, string $remarks): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $tenant = $this->CI->tenant_model->find_for_update($tenant_id);
            if ($tenant === NULL || $tenant['status'] !== 'Active')
            {
                throw new DomainException('This tenant has already moved out.');
            }
            if ($move_out_date < $tenant['move_in_date'])
            {
                throw new DomainException('The move-out date cannot be before the move-in date ('.fmt_date($tenant['move_in_date']).').');
            }
            if ($move_out_date > date('Y-m-d'))
            {
                throw new DomainException('The move-out date cannot be in the future.');
            }

            $update = array(
                'status'        => 'Moved Out',
                'move_out_date' => $move_out_date,
                'updated_by'    => user_id(),
            );
            if ($remarks !== '')
            {
                $update['remarks'] = mb_substr(trim(($tenant['remarks'] ? $tenant['remarks']."\n" : '').'Move-out: '.$remarks), 0, 500);
            }
            $this->CI->tenant_model->update($tenant_id, $update);
            $this->CI->house_model->find_for_update((int) $tenant['house_id']);
            $this->CI->house_model->update((int) $tenant['house_id'], array('occupancy_status' => $occupancy_after, 'updated_by' => user_id()));
            $this->CI->audit->log('Tenant Moved Out', 'tenants', $tenant_id, array('status' => 'Active'), $update + array('house_occupancy' => $occupancy_after));

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The move-out could not be saved.');
            }
            $db->trans_commit();
            return array('success' => TRUE, 'message' => $tenant['tenant_name'].' moved out on '.fmt_date($move_out_date).'. The house is now '.$occupancy_after.'.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'move out');
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function failure(Throwable $e, string $context): array
    {
        if ($e instanceof DomainException)
        {
            return array('success' => FALSE, 'message' => $e->getMessage());
        }
        log_message('error', 'Tenant_service '.$context.': '.$e->getMessage().' | DB: '.json_encode($this->CI->db->error()));
        return array('success' => FALSE, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'An unexpected error occurred. Please try again.');
    }
}
