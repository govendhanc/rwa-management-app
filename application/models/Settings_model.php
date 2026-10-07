<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Association settings (single row) and system settings (key/value).
 * Both are cached for the duration of the request.
 */
class Settings_model extends MY_Model
{
    protected $table = 'association_settings';

    /** @var array<string, mixed>|null */
    private $association_cache = NULL;

    /** @var array<string, string|null>|null */
    private $system_cache = NULL;

    /**
     * Fields that may be updated from Settings > Association.
     *
     * @var string[]
     */
    private $association_fields = array(
        'association_name', 'short_name', 'registration_no', 'address', 'city', 'district', 'state',
        'pincode', 'mobile', 'email', 'website', 'logo_path', 'bank_name', 'bank_account_no', 'bank_ifsc',
        'bank_branch', 'upi_id', 'default_due_day', 'receipt_prefix',
        'currency_symbol', 'receipt_footer_note',
    );

    /**
     * @return array<string, mixed>
     */
    public function association(): array
    {
        if ($this->association_cache === NULL)
        {
            $row = $this->db->get_where('association_settings', array('id' => 1), 1)->row_array();
            $this->association_cache = $row ?: array(
                'association_name' => 'Residential Owners Welfare Association',
                'currency_symbol'  => '₹',
                'receipt_prefix'   => 'REC',
                'default_due_day'  => 10,
            );
        }
        return $this->association_cache;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update_association(array $data, int $user_id): bool
    {
        $data = array_intersect_key($data, array_flip($this->association_fields));
        $data['updated_by'] = $user_id;
        $this->association_cache = NULL;
        return (bool) $this->db->where('id', 1)->update('association_settings', $data);
    }

    public function get(string $key, ?string $default = NULL): ?string
    {
        if ($this->system_cache === NULL)
        {
            $this->system_cache = array();
            foreach ($this->db->get('system_settings')->result_array() as $row)
            {
                $this->system_cache[$row['setting_key']] = $row['setting_value'];
            }
        }
        if (array_key_exists($key, $this->system_cache) && $this->system_cache[$key] !== NULL && $this->system_cache[$key] !== '')
        {
            return $this->system_cache[$key];
        }
        return $default;
    }

    /**
     * @return array<string, string|null>
     */
    public function all_system(): array
    {
        $this->get('__warm_cache__');
        return $this->system_cache;
    }

    public function set(string $key, ?string $value): bool
    {
        $this->system_cache = NULL;
        $sql = 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
        return (bool) $this->db->query($sql, array($key, $value));
    }

    public function get_int(string $key, int $default): int
    {
        $value = $this->get($key);
        return ($value !== NULL && ctype_digit($value)) ? (int) $value : $default;
    }
}
