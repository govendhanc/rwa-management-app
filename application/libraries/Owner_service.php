<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Owner & house business operations that touch more than one table.
 *
 * All public methods run inside a database transaction and return
 * array{success: bool, message: string, id?: int}.
 */
class Owner_service
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('owner_model', 'house_model'));
        $this->CI->load->library('audit');
    }

    /**
     * Create an owner, generate the OWNxxxxx code and link any number of plots.
     *
     * Each plot row is either
     *   array('mode' => 'existing', 'house_id' => 12, 'occupancy_status' => '...')
     *   array('mode' => 'new', 'house' => array(<houses columns>), 'occupancy_status' => '...')
     *
     * @param array<string, mixed>             $owner Owner columns
     * @param array<int, array<string, mixed>> $plots
     * @return array<string, mixed>
     */
    public function create_owner(array $owner, array $plots): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $owner['created_by'] = user_id();
            $owner['updated_by'] = user_id();
            if ($owner['status'] === 'Inactive')
            {
                $owner += $this->deactivation_fields('Added as inactive');
            }
            $owner_id = $this->CI->owner_model->insert($owner);
            if ($owner_id === 0)
            {
                throw new RuntimeException('The owner could not be saved.');
            }

            $code = 'OWN'.str_pad((string) $owner_id, 5, '0', STR_PAD_LEFT);
            $this->CI->owner_model->update($owner_id, array('owner_code' => $code));

            $labels = array();
            foreach ($plots as $plot)
            {
                $labels[] = $this->link_plot($owner_id, $plot);
            }

            $this->CI->audit->log('Owner Created', 'owners', $owner_id, NULL, $owner + array('owner_code' => $code, 'plots' => $labels));

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The owner could not be saved.');
            }
            $db->trans_commit();

            $message = 'Owner '.$code.' - '.$owner['owner_name'].' created';
            if ( ! empty($labels))
            {
                $message .= ' with '.count($labels).' plot(s): '.implode('; ', $labels);
            }
            return array('success' => TRUE, 'message' => $message.'.', 'id' => $owner_id);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'create owner');
        }
    }

    /**
     * Assign one more plot (existing unassigned, or new) to an existing active owner.
     *
     * @param array<string, mixed> $plot Same shape as a create_owner() plot row
     * @return array<string, mixed>
     */
    public function assign_plot(int $owner_id, array $plot): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $owner = $this->CI->owner_model->find_for_update($owner_id);
            if ($owner === NULL || (int) $owner['is_deleted'] === 1 || $owner['status'] !== 'Active')
            {
                throw new DomainException('Plots can only be assigned to an active owner.');
            }
            $label = $this->link_plot($owner_id, $plot);
            $this->CI->audit->log('Plot Assigned', 'owners', $owner_id, NULL, array('plot' => $label));
            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The plot could not be assigned.');
            }
            $db->trans_commit();
            return array('success' => TRUE, 'message' => $label.' assigned to '.$owner['owner_code'].' - '.$owner['owner_name'].'.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'assign plot');
        }
    }

    /**
     * Link one plot to an owner inside the caller's transaction. Returns the plot label.
     *
     * @param array<string, mixed> $plot
     */
    private function link_plot(int $owner_id, array $plot): string
    {
        $occupancy = (string) $plot['occupancy_status'];

        if ($plot['mode'] === 'existing')
        {
            $house = $this->CI->house_model->find_for_update((int) $plot['house_id']);
            if ($house === NULL || $house['status'] !== 'Active')
            {
                throw new DomainException('The selected plot is not available.');
            }
            if ($house['owner_id'] !== NULL)
            {
                throw new DomainException('Plot '.$house['plot_no'].' is already assigned to another owner. Please choose another plot.');
            }
            if ($occupancy === 'Tenant Occupied')
            {
                $occupancy = 'Owner Occupied';
            }
            $this->CI->house_model->update((int) $house['id'], array(
                'owner_id'         => $owner_id,
                'occupancy_status' => $occupancy,
                'updated_by'       => user_id(),
            ));
            return $this->CI->house_model->label($house);
        }

        $house = $plot['house'];
        // A brand-new plot cannot already have a tenant record; tenants are added afterwards
        $house['occupancy_status'] = $occupancy === 'Tenant Occupied' ? 'Owner Occupied' : $occupancy;
        $house['owner_id'] = $owner_id;
        $house['status'] = 'Active';
        $house['created_by'] = user_id();
        $house['updated_by'] = user_id();
        $house_id = $this->CI->house_model->insert($house);
        if ($house_id === 0)
        {
            throw new RuntimeException('Plot '.$house['plot_no'].' could not be created.');
        }
        $this->CI->audit->log('House Created', 'houses', $house_id, NULL, $house);
        return $this->CI->house_model->label($house);
    }

    /**
     * Edit owner details. A status change made here is handled exactly like
     * Deactivate / Reactivate (who, when, audit). Plots are edited under Houses.
     *
     * @param array<string, mixed> $old  Current row
     * @param array<string, mixed> $data New values
     * @return array<string, mixed>
     */
    public function update_owner(array $old, array $data): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $current = $this->CI->owner_model->find_for_update((int) $old['id']);
            if ($current === NULL || (int) $current['is_deleted'] === 1)
            {
                throw new DomainException('Owner not found.');
            }

            $changed = FALSE;
            foreach ($data as $key => $value)
            {
                if (trim((string) $current[$key]) !== trim((string) $value))
                {
                    $changed = TRUE;
                    break;
                }
            }
            if ( ! $changed)
            {
                $db->trans_rollback();
                return array('success' => TRUE, 'message' => 'No changes were made to '.$current['owner_code'].'.');
            }

            $action = 'Owner Updated';
            if ($data['status'] !== $current['status'])
            {
                if ($data['status'] === 'Inactive')
                {
                    $data += $this->deactivation_fields('Status changed on the Edit Owner form');
                    $action = 'Owner Deactivated';
                }
                else
                {
                    $data += $this->reactivation_fields();
                    $action = 'Owner Reactivated';
                }
            }

            $data['updated_by'] = user_id();
            $this->CI->owner_model->update((int) $current['id'], $data);
            $this->CI->audit->log($action, 'owners', $current['id'], $current, $data);

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The owner could not be updated.');
            }
            $db->trans_commit();

            $message = 'Owner '.$current['owner_code'].' updated.';
            if ($action === 'Owner Deactivated')
            {
                $message .= ' The owner is now inactive and will not be billed; all history is kept.';
            }
            elseif ($action === 'Owner Reactivated')
            {
                $message .= ' The owner is active again and will be included in future maintenance generation.';
            }
            return array('success' => TRUE, 'message' => $message);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'update owner');
        }
    }

    /**
     * Deactivate (owner sold the property / left the association).
     * Nothing is deleted: bills, payments, receipts, advance credit, tenants and plots stay
     * exactly as they are. The owner is simply not billed from the next generation onwards,
     * and outstanding dues can still be collected.
     *
     * @return array<string, mixed>
     */
    public function deactivate_owner(int $owner_id, string $reason): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $owner = $this->CI->owner_model->find_for_update($owner_id);
            if ($owner === NULL || (int) $owner['is_deleted'] === 1)
            {
                throw new DomainException('Owner not found.');
            }
            if ($owner['status'] === 'Inactive')
            {
                throw new DomainException('Owner '.$owner['owner_code'].' is already inactive.');
            }

            $balance = $this->CI->owner_model->find_active_record($owner_id);
            $data = array('status' => 'Inactive', 'updated_by' => user_id()) + $this->deactivation_fields($reason);
            $this->CI->owner_model->update($owner_id, $data);
            $this->CI->audit->log('Owner Deactivated', 'owners', $owner_id,
                array('status' => $owner['status']),
                array(
                    'status'                         => 'Inactive',
                    'deactivation_reason'            => $data['deactivation_reason'],
                    'outstanding_at_deactivation'    => $balance['outstanding'],
                    'advance_credit_at_deactivation' => $balance['advance_credit'],
                )
            );

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The owner could not be deactivated.');
            }
            $db->trans_commit();

            $message = 'Owner '.$owner['owner_code'].' - '.$owner['owner_name'].' deactivated. No new maintenance will be generated for this owner; all past records are kept.';
            if (to_paise_signed($balance['outstanding']) > 0)
            {
                $message .= ' Outstanding '.money($balance['outstanding']).' is still due and can be collected.';
            }
            if (to_paise_signed($balance['advance_credit']) > 0)
            {
                $message .= ' Advance credit of '.money($balance['advance_credit']).' is kept.';
            }
            return array('success' => TRUE, 'message' => $message);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'deactivate owner');
        }
    }

    /**
     * Reactivate: eligible for maintenance generation again. History is unchanged.
     *
     * @return array<string, mixed>
     */
    public function reactivate_owner(int $owner_id): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $owner = $this->CI->owner_model->find_for_update($owner_id);
            if ($owner === NULL || (int) $owner['is_deleted'] === 1)
            {
                throw new DomainException('Owner not found.');
            }
            if ($owner['status'] === 'Active')
            {
                throw new DomainException('Owner '.$owner['owner_code'].' is already active.');
            }

            $data = array('status' => 'Active', 'updated_by' => user_id()) + $this->reactivation_fields();
            $this->CI->owner_model->update($owner_id, $data);
            $this->CI->audit->log('Owner Reactivated', 'owners', $owner_id,
                array('status' => 'Inactive', 'deactivated_at' => $owner['deactivated_at'], 'deactivation_reason' => $owner['deactivation_reason']),
                array('status' => 'Active', 'deactivated_at' => NULL, 'deactivation_reason' => NULL)
            );

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The owner could not be reactivated.');
            }
            $db->trans_commit();

            $plots = $this->CI->db->where('owner_id', $owner_id)->where('status', 'Active')->count_all_results('houses');
            $message = 'Owner '.$owner['owner_code'].' - '.$owner['owner_name'].' is active again and will be included in future maintenance generation.';
            if ($plots === 0)
            {
                $message .= ' Note: no plot is assigned, so nothing will be billed until a plot is assigned.';
            }
            return array('success' => TRUE, 'message' => $message);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'reactivate owner');
        }
    }

    /**
     * @return array{deactivated_at: string, deactivated_by: int|null, deactivation_reason: string}
     */
    private function deactivation_fields(string $reason): array
    {
        $reason = trim($reason);
        return array(
            'deactivated_at'      => date('Y-m-d H:i:s'),
            'deactivated_by'      => user_id(),
            'deactivation_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : 'Not specified',
        );
    }

    /**
     * @return array{deactivated_at: null, deactivated_by: null, deactivation_reason: null}
     */
    private function reactivation_fields(): array
    {
        return array('deactivated_at' => NULL, 'deactivated_by' => NULL, 'deactivation_reason' => NULL);
    }

    /**
     * Remove an owner entered by mistake (soft delete: the row is kept with is_deleted = 1).
     * Only for owners without maintenance or payment history - everyone else is deactivated.
     * Their houses become unassigned and vacant.
     *
     * @return array<string, mixed>
     */
    public function delete_owner(int $owner_id): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $owner = $this->CI->owner_model->find_for_update($owner_id);
            if ($owner === NULL || (int) $owner['is_deleted'] === 1)
            {
                throw new DomainException('Owner not found.');
            }
            if ($this->CI->owner_model->has_financial_history($owner_id))
            {
                throw new DomainException('This owner has maintenance or payment history and cannot be removed. Deactivate the owner instead; all records are kept.');
            }

            $houses = $this->CI->house_model->for_owner($owner_id);
            foreach ($houses as $house)
            {
                $this->CI->house_model->update((int) $house['id'], array('owner_id' => NULL, 'occupancy_status' => 'Vacant', 'updated_by' => user_id()));
            }

            $this->CI->owner_model->update($owner_id, array(
                'is_deleted' => 1,
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => user_id(),
                'status'     => 'Inactive',
                'updated_by' => user_id(),
            ));
            $this->CI->audit->log('Owner Removed', 'owners', $owner_id, $owner, array('is_deleted' => 1, 'houses_released' => array_column($houses, 'plot_no')));

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('Delete failed.');
            }
            $db->trans_commit();
            return array('success' => TRUE, 'message' => 'Owner '.$owner['owner_code'].' removed.'.(count($houses) ? ' '.count($houses).' house(s) are now unassigned.' : ''));
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'delete owner');
        }
    }

    /**
     * Create a house, optionally assigned to an owner.
     *
     * @param array<string, mixed> $house
     * @return array<string, mixed>
     */
    public function create_house(array $house): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            if ($house['owner_id'] !== NULL)
            {
                $this->assert_owner_assignable((int) $house['owner_id']);
            }
            $house['created_by'] = user_id();
            $house['updated_by'] = user_id();
            $id = $this->CI->house_model->insert($house);
            if ($id === 0)
            {
                throw new RuntimeException('The house could not be saved.');
            }
            $this->CI->audit->log('House Created', 'houses', $id, NULL, $house);
            $db->trans_commit();
            return array('success' => TRUE, 'message' => 'Plot '.$house['plot_no'].' created.', 'id' => $id);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'create house');
        }
    }

    /**
     * Update a house. Changing the owner is allowed: past bills stay with the
     * owner they were issued to; future bills go to the new owner.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update_house(int $house_id, array $data): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $old = $this->CI->house_model->find_for_update($house_id);
            if ($old === NULL)
            {
                throw new DomainException('House not found.');
            }
            $new_owner = $data['owner_id'];
            $owner_changed = (string) $old['owner_id'] !== (string) $new_owner;
            if ($owner_changed && $new_owner !== NULL)
            {
                $this->assert_owner_assignable((int) $new_owner);
            }
            if ($new_owner === NULL)
            {
                $data['occupancy_status'] = 'Vacant';
            }

            $data['updated_by'] = user_id();
            $this->CI->house_model->update($house_id, $data);
            if ($owner_changed && $new_owner !== NULL)
            {
                // A tenant living in a transferred house now rents from the new owner
                $db->where('house_id', $house_id)->where('status', 'Active')
                    ->update('tenants', array('owner_id' => (int) $new_owner, 'updated_by' => user_id()));
            }
            $this->CI->audit->log($owner_changed ? 'House Owner Changed' : 'House Updated', 'houses', $house_id, $old, $data);

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('Update failed.');
            }
            $db->trans_commit();

            $message = 'Plot '.$data['plot_no'].' updated.';
            if ($owner_changed && $old['owner_id'] !== NULL)
            {
                $due = $this->CI->house_model->outstanding_for_owner($house_id, (int) $old['owner_id']);
                if (to_paise_signed($due) > 0)
                {
                    $message .= ' Note: the previous owner still owes '.money($due).' for this house.';
                }
            }
            return array('success' => TRUE, 'message' => $message);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'update house');
        }
    }

    private function assert_owner_assignable(int $owner_id): void
    {
        $owner = $this->CI->owner_model->find($owner_id);
        if ($owner === NULL || (int) $owner['is_deleted'] === 1 || $owner['status'] !== 'Active')
        {
            throw new DomainException('The selected owner is not active.');
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function failure(Throwable $e, string $context): array
    {
        if ($e instanceof DomainException || $e instanceof RuntimeException)
        {
            if ( ! ($e instanceof DomainException))
            {
                log_message('error', 'Owner_service '.$context.': '.$e->getMessage().' | DB: '.json_encode($this->CI->db->error()));
            }
            return array('success' => FALSE, 'message' => $e->getMessage());
        }
        log_message('error', 'Owner_service '.$context.' exception: '.$e->getMessage());
        return array('success' => FALSE, 'message' => 'An unexpected error occurred. Please try again.');
    }
}
