<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Owner import from CSV (Excel: File > Save As > CSV UTF-8).
 *
 * Columns (header row, any order, case-insensitive; common spellings accepted):
 *   Plot No*, House No, Block, Street, Owner Name*, Co-owner, Mobile*, WhatsApp, Email, Owner Type, Built Status
 * "Maintenance Amount" may be present but is ignored: amounts come from Maintenance Rates.
 *
 * Rules
 *  - A plot number already in the portal, or repeated in the file, is a Duplicate.
 *  - A house number already used in the same block (portal or file) is a Duplicate.
 *  - Rows with the same owner name + mobile become ONE owner with several plots.
 *  - If an active owner with the same name + mobile already exists, the plots are added to that owner.
 *  - Each owner is saved in its own transaction; a failure affects only that owner's rows.
 */
class Owner_import
{
    const MAX_ROWS = 2000;

    /** @var array<string, string[]> canonical column => accepted header spellings */
    const COLUMNS = array(
        'plot_no'       => array('plot no', 'plot', 'plot number', 'plot_no', 'plot no.'),
        'house_no'      => array('house no', 'house', 'house number', 'house_no', 'door no', 'house no.'),
        'block'         => array('block', 'block no'),
        'street'        => array('street', 'street name'),
        'owner_name'    => array('owner name', 'owner', 'name', 'owner_name'),
        'co_owner_name' => array('co-owner', 'co owner', 'co-owner name', 'co_owner_name', 'co owner name'),
        'mobile'        => array('mobile', 'mobile no', 'mobile number', 'phone', 'phone no'),
        'whatsapp_no'   => array('whatsapp', 'whatsapp no', 'whatsapp number', 'whatsapp_no'),
        'email'         => array('email', 'e-mail', 'email id', 'mail'),
        'owner_type'    => array('owner type', 'type', 'owner_type'),
        'built_status'  => array('built status', 'built', 'construction', 'construction status', 'built_status'),
        'maintenance'   => array('maintenance amount', 'maintenance', 'amount'),
    );

    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('owner_model', 'house_model'));
        $this->CI->load->library(array('owner_service', 'form_validation'));
    }

    /**
     * Validate (and unless $dry_run, import) a CSV file.
     *
     * @return array{success: bool, message: string, total: int, imported: int, failed: int, duplicate: int,
     *               owners_created: int, owners_extended: int, rows: array<int, array<string, mixed>>, headers: string[]}
     */
    public function run(string $csv_path, string $maintenance_start, bool $dry_run): array
    {
        $parsed = $this->parse($csv_path);
        if ( ! $parsed['success'])
        {
            return $parsed + array('total' => 0, 'imported' => 0, 'failed' => 0, 'duplicate' => 0, 'owners_created' => 0, 'owners_extended' => 0, 'rows' => array(), 'headers' => array());
        }
        $rows = $parsed['rows'];

        // Existing plots / house numbers
        $existing_plots = array();
        $existing_houses = array();
        foreach ($this->CI->db->select('plot_no, block, house_no')->get('houses')->result_array() as $h)
        {
            $existing_plots[mb_strtolower(trim($h['plot_no']))] = TRUE;
            if ($h['house_no'] !== NULL && $h['house_no'] !== '')
            {
                $existing_houses[mb_strtolower(trim($h['block']).'|'.trim($h['house_no']))] = TRUE;
            }
        }

        $seen_plots = array();
        $seen_houses = array();
        $groups = array();
        foreach ($rows as $i => &$row)
        {
            $errors = $this->validate_row($row['data']);
            if ( ! empty($errors))
            {
                $row['status'] = 'Failed';
                $row['reason'] = implode('; ', $errors);
                continue;
            }
            $d = $row['data'];
            $plot_key = mb_strtolower($d['plot_no']);
            $house_key = $d['house_no'] !== '' ? mb_strtolower($d['block'].'|'.$d['house_no']) : NULL;
            if (isset($existing_plots[$plot_key]))
            {
                $row['status'] = 'Duplicate';
                $row['reason'] = 'Plot '.$d['plot_no'].' already exists in the portal';
                continue;
            }
            if (isset($seen_plots[$plot_key]))
            {
                $row['status'] = 'Duplicate';
                $row['reason'] = 'Plot '.$d['plot_no'].' is repeated in the file (row '.$seen_plots[$plot_key].')';
                continue;
            }
            if ($house_key !== NULL && (isset($existing_houses[$house_key]) || isset($seen_houses[$house_key])))
            {
                $row['status'] = 'Duplicate';
                $row['reason'] = 'House '.$d['house_no'].($d['block'] !== '' ? ' (Block '.$d['block'].')' : '').' already exists';
                continue;
            }
            $seen_plots[$plot_key] = $row['line'];
            if ($house_key !== NULL)
            {
                $seen_houses[$house_key] = $row['line'];
            }
            $row['status'] = 'Valid';
            $owner_key = normalize_mobile($d['mobile']).'|'.mb_strtolower(preg_replace('/\s+/', ' ', $d['owner_name']));
            $groups[$owner_key][] = $i;
        }
        unset($row);

        $owners_created = 0;
        $owners_extended = 0;
        foreach ($groups as $indexes)
        {
            $first = $rows[$indexes[0]]['data'];
            $existing = $this->find_existing_owner($first['owner_name'], $first['mobile']);

            if ($dry_run)
            {
                foreach ($indexes as $i)
                {
                    $rows[$i]['status'] = 'Ready';
                    $rows[$i]['reason'] = ($existing ? 'Will be added to existing owner '.$existing['owner_code'] : 'Will create owner').(count($indexes) > 1 ? ' ('.count($indexes).' plots)' : '');
                }
                continue;
            }

            $plots = array();
            foreach ($indexes as $i)
            {
                $d = $rows[$i]['data'];
                $plots[] = array(
                    'mode'             => 'new',
                    'occupancy_status' => $d['built_status'] === 'Built' ? 'Owner Occupied' : 'Vacant',
                    'house'            => array(
                        'plot_no'      => $d['plot_no'],
                        'house_no'     => $d['house_no'] !== '' ? $d['house_no'] : NULL,
                        'block'        => $d['block'],
                        'street'       => $d['street'] !== '' ? $d['street'] : NULL,
                        'house_type'   => $d['built_status'] === 'Built' ? 'Independent House' : 'Vacant Plot',
                        'built_status' => $d['built_status'],
                    ),
                );
            }

            if ($existing)
            {
                $ok = TRUE;
                $message = '';
                foreach ($plots as $n => $plot)
                {
                    $result = $this->CI->owner_service->assign_plot((int) $existing['id'], $plot);
                    $rows[$indexes[$n]]['status'] = $result['success'] ? 'Imported' : 'Failed';
                    $rows[$indexes[$n]]['reason'] = $result['success'] ? 'Added to existing owner '.$existing['owner_code'] : $result['message'];
                    $ok = $ok && $result['success'];
                }
                $owners_extended += $ok ? 1 : 0;
                continue;
            }

            $email = strtolower($first['email']);
            $whatsapp = normalize_mobile($first['whatsapp_no']);
            $result = $this->CI->owner_service->create_owner(array(
                'owner_name'             => $first['owner_name'],
                'co_owner_name'          => $first['co_owner_name'] !== '' ? $first['co_owner_name'] : NULL,
                'mobile'                 => normalize_mobile($first['mobile']),
                'whatsapp_no'            => $whatsapp !== '' ? $whatsapp : normalize_mobile($first['mobile']),
                'email'                  => $email !== '' ? $email : NULL,
                'owner_type'             => $first['owner_type'],
                'joining_date'           => date('Y-m-d'),
                'maintenance_start_date' => $maintenance_start,
                'status'                 => 'Active',
                'remarks'                => 'Imported from CSV',
            ), $plots);
            foreach ($indexes as $i)
            {
                $rows[$i]['status'] = $result['success'] ? 'Imported' : 'Failed';
                $rows[$i]['reason'] = $result['success'] ? 'Owner '.$this->CI->db->select('owner_code')->get_where('owners', array('id' => $result['id']))->row()->owner_code.' created' : $result['message'];
            }
            $owners_created += $result['success'] ? 1 : 0;
        }

        $counts = array('Imported' => 0, 'Ready' => 0, 'Failed' => 0, 'Duplicate' => 0);
        foreach ($rows as $row)
        {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
        }

        return array(
            'success'         => TRUE,
            'message'         => $dry_run ? 'Validation complete. Nothing was saved.' : 'Import complete.',
            'total'           => count($rows),
            'imported'        => $dry_run ? $counts['Ready'] : $counts['Imported'],
            'failed'          => $counts['Failed'],
            'duplicate'       => $counts['Duplicate'],
            'owners_created'  => $owners_created,
            'owners_extended' => $owners_extended,
            'rows'            => $rows,
            'headers'         => $parsed['headers'],
        );
    }

    /**
     * Write the per-row result as a CSV (for the error report download). Returns the path relative to STORAGEPATH.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $headers
     */
    public function write_report(array $rows, array $headers, int $batch_id): string
    {
        $relative = 'imports/errors/import-'.$batch_id.'-'.bin2hex(random_bytes(6)).'.csv';
        $fh = fopen(STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $relative), 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_merge(array('Row', 'Result', 'Reason'), $headers));
        foreach ($rows as $row)
        {
            fputcsv($fh, array_merge(array($row['line'], $row['status'], $row['reason']), array_map(array($this, 'csv_safe'), $row['raw'])));
        }
        fclose($fh);
        return $relative;
    }

    /**
     * Prevent spreadsheet formula injection when the report is opened in Excel.
     */
    public function csv_safe(string $value): string
    {
        return ($value !== '' && in_array($value[0], array('=', '+', '-', '@'), TRUE) && ! is_numeric($value)) ? "'".$value : $value;
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array{success: bool, message: string, rows?: array<int, array<string, mixed>>, headers?: string[]}
     */
    private function parse(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === FALSE)
        {
            return array('success' => FALSE, 'message' => 'The file could not be read.');
        }
        $header = fgetcsv($fh);
        if ( ! is_array($header) || count(array_filter($header, 'strlen')) === 0)
        {
            fclose($fh);
            return array('success' => FALSE, 'message' => 'The file is empty or has no header row.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(function ($h) { return trim((string) $h); }, $header);

        $map = array();
        foreach ($header as $index => $name)
        {
            $key = mb_strtolower(preg_replace('/\s+/', ' ', $name));
            foreach (self::COLUMNS as $canonical => $aliases)
            {
                if (in_array($key, $aliases, TRUE) && ! isset($map[$canonical]))
                {
                    $map[$canonical] = $index;
                }
            }
        }
        $missing = array_diff(array('plot_no', 'owner_name', 'mobile'), array_keys($map));
        if ( ! empty($missing))
        {
            fclose($fh);
            $names = array('plot_no' => 'Plot No', 'owner_name' => 'Owner Name', 'mobile' => 'Mobile');
            return array('success' => FALSE, 'message' => 'Missing required column(s): '.implode(', ', array_map(function ($m) use ($names) { return $names[$m]; }, $missing)).'. Download the template for the expected format.');
        }

        $rows = array();
        $line = 1;
        while (($cols = fgetcsv($fh)) !== FALSE)
        {
            $line++;
            if ( ! is_array($cols) || count(array_filter($cols, function ($c) { return trim((string) $c) !== ''; })) === 0)
            {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS)
            {
                fclose($fh);
                return array('success' => FALSE, 'message' => 'The file has more than '.self::MAX_ROWS.' rows. Please split it into smaller files.');
            }
            $data = array();
            foreach (array_keys(self::COLUMNS) as $canonical)
            {
                $value = isset($map[$canonical], $cols[$map[$canonical]]) ? (string) $cols[$map[$canonical]] : '';
                if ( ! mb_check_encoding($value, 'UTF-8'))
                {
                    $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                }
                $data[$canonical] = trim(strip_tags($value));
            }
            $raw = array();
            foreach ($header as $index => $name)
            {
                $raw[] = isset($cols[$index]) ? (string) $cols[$index] : '';
            }
            $rows[] = array('line' => $line, 'data' => $data, 'raw' => $raw, 'status' => '', 'reason' => '');
        }
        fclose($fh);

        if (empty($rows))
        {
            return array('success' => FALSE, 'message' => 'The file has a header row but no data rows.');
        }
        return array('success' => TRUE, 'message' => '', 'rows' => $rows, 'headers' => $header);
    }

    /**
     * Validate and normalise one row in place. Returns error messages.
     *
     * @param array<string, string> $d
     * @return string[]
     */
    private function validate_row(array &$d): array
    {
        $v = $this->CI->form_validation;
        $errors = array();
        if ($d['plot_no'] === '' || mb_strlen($d['plot_no']) > 20)
        {
            $errors[] = 'Plot No is required (max 20 characters)';
        }
        if (mb_strlen($d['house_no']) > 20 || mb_strlen($d['block']) > 20 || mb_strlen($d['street']) > 100)
        {
            $errors[] = 'House No/Block (max 20) or Street (max 100) too long';
        }
        if (mb_strlen($d['owner_name']) < 2 || mb_strlen($d['owner_name']) > 120)
        {
            $errors[] = 'Owner Name is required (2-120 characters)';
        }
        if ($d['mobile'] === '' || ! $v->valid_mobile($d['mobile']))
        {
            $errors[] = 'Mobile must be a valid 10-digit number';
        }
        if ($d['whatsapp_no'] !== '' && ! $v->valid_mobile($d['whatsapp_no']))
        {
            $errors[] = 'WhatsApp number is not valid';
        }
        if ($d['email'] !== '' && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL))
        {
            $errors[] = 'Email is not valid';
        }

        $types = (array) $this->CI->config->item('owner_types');
        $type = ucfirst(strtolower($d['owner_type']));
        $d['owner_type'] = $d['owner_type'] === '' ? 'Individual' : (strtoupper($d['owner_type']) === 'NRI' ? 'NRI' : $type);
        if ( ! in_array($d['owner_type'], $types, TRUE))
        {
            $errors[] = 'Owner Type must be one of: '.implode(', ', $types);
        }

        $built = strtolower($d['built_status']);
        if ($built === '' || in_array($built, array('built', 'constructed', 'house', 'yes'), TRUE))
        {
            $d['built_status'] = 'Built';
        }
        elseif (in_array($built, array('not built', 'vacant', 'empty', 'plot', 'vacant plot', 'no', 'land'), TRUE))
        {
            $d['built_status'] = 'Not Built';
        }
        elseif (in_array($built, array('under construction', 'construction', 'building'), TRUE))
        {
            $d['built_status'] = 'Under Construction';
        }
        else
        {
            $errors[] = 'Built Status must be Built, Not Built or Under Construction';
        }
        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_existing_owner(string $name, string $mobile): ?array
    {
        $row = $this->CI->db->query(
            "SELECT id, owner_code FROM owners WHERE is_deleted = 0 AND status = 'Active' AND mobile = ? AND LOWER(owner_name) = LOWER(?) LIMIT 1",
            array(normalize_mobile($mobile), preg_replace('/\s+/', ' ', trim($name)))
        )->row_array();
        return $row ?: NULL;
    }
}
