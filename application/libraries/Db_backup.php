<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MySQL / MariaDB backup to storage/backups/backup_<date>_<time>.sql.gz (outside the web root).
 *
 * 1. Uses mysqldump when MYSQLDUMP_PATH (.env) points to an executable and exec() is allowed.
 *    The password is passed in the MYSQL_PWD environment variable, never on the command line.
 * 2. Otherwise a built-in PHP dumper: tables (structure + data, generated columns skipped),
 *    then views (without DEFINER so they restore on shared hosting).
 * Database credentials are never shown in the UI.
 */
class Db_backup
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function directory(): string
    {
        return STORAGEPATH.'backups'.DIRECTORY_SEPARATOR;
    }

    /**
     * @return array{success: bool, message: string, file?: string, size?: int, method?: string}
     */
    public function create(): array
    {
        $dir = $this->directory();
        if ( ! is_dir($dir) || ! is_really_writable($dir))
        {
            return array('success' => FALSE, 'message' => 'The backup folder (storage/backups) is not writable.');
        }
        @set_time_limit(300);
        $name = 'backup_'.date('Y-m-d_His').'_'.bin2hex(random_bytes(3)).'.sql.gz';
        $path = $dir.$name;

        $method = 'php';
        $dump_ok = FALSE;
        if ($this->mysqldump_available())
        {
            $dump_ok = $this->run_mysqldump($path);
            $method = $dump_ok ? 'mysqldump' : 'php';
        }
        if ( ! $dump_ok)
        {
            try
            {
                $this->run_php_dump($path);
                $dump_ok = TRUE;
            }
            catch (Throwable $e)
            {
                @unlink($path);
                log_message('error', 'PHP backup failed: '.$e->getMessage());
                return array('success' => FALSE, 'message' => 'The backup could not be created. See the error log.');
            }
        }

        $size = (int) filesize($path);
        $this->CI->db->insert('database_backups', array('file_name' => $name, 'file_size' => $size, 'method' => $method, 'created_by' => user_id()));
        return array('success' => TRUE, 'message' => 'Backup created ('.$this->human_size($size).', '.$method.').', 'file' => $name, 'size' => $size, 'method' => $method);
    }

    /**
     * Absolute path of a stored backup file name, or NULL if invalid/missing.
     */
    public function path_of(string $file_name): ?string
    {
        if ( ! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{6}_[a-f0-9]{6}\.sql\.gz$/', $file_name))
        {
            return NULL;
        }
        $path = $this->directory().$file_name;
        return is_file($path) ? $path : NULL;
    }

    public function human_size(int $bytes): string
    {
        if ($bytes >= 1048576)
        {
            return round($bytes / 1048576, 1).' MB';
        }
        return max(1, (int) round($bytes / 1024)).' KB';
    }

    /* ------------------------------------------------------------------ */

    private function mysqldump_available(): bool
    {
        $bin = (string) env('MYSQLDUMP_PATH', '');
        if ($bin === '' || ! is_file($bin) || ! function_exists('proc_open'))
        {
            return FALSE;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return ! in_array('proc_open', $disabled, TRUE);
    }

    private function run_mysqldump(string $gz_path): bool
    {
        $db = $this->CI->db;
        $cmd = array(
            (string) env('MYSQLDUMP_PATH'),
            '--host='.$db->hostname,
            '--port='.(int) ($db->port ?: 3306),
            '--user='.$db->username,
            '--single-transaction', '--routines', '--triggers', '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            $db->database,
        );
        $env = array('MYSQL_PWD' => (string) $db->password);
        foreach (array('SystemRoot', 'PATH', 'TEMP', 'TMP') as $keep)
        {
            if (getenv($keep) !== FALSE)
            {
                $env[$keep] = getenv($keep);
            }
        }
        $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, NULL, $env);
        if ( ! is_resource($proc))
        {
            return FALSE;
        }
        $gz = gzopen($gz_path, 'wb6');
        $bytes = 0;
        while ( ! feof($pipes[1]))
        {
            $chunk = fread($pipes[1], 65536);
            if ($chunk === FALSE)
            {
                break;
            }
            $bytes += strlen($chunk);
            gzwrite($gz, $chunk);
        }
        gzclose($gz);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0 || $bytes < 100)
        {
            log_message('error', 'mysqldump failed (exit '.$code.'): '.mb_substr((string) $stderr, 0, 500));
            @unlink($gz_path);
            return FALSE;
        }
        return TRUE;
    }

    private function run_php_dump(string $gz_path): void
    {
        $db = $this->CI->db;
        $gz = gzopen($gz_path, 'wb6');
        $w = function (string $s) use ($gz) { gzwrite($gz, $s); };

        $w("-- ROWA Portal database backup\n-- Database: ".$db->database."\n-- Created: ".date('Y-m-d H:i:s')."\n-- Method: PHP\n\n");
        $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");

        $objects = $db->query('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->result_array();
        $views = array();
        foreach ($objects as $o)
        {
            if ($o['TABLE_TYPE'] === 'VIEW')
            {
                $views[] = $o['TABLE_NAME'];
                continue;
            }
            $table = $o['TABLE_NAME'];
            $create = $db->query('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`')->row_array();
            $w("DROP TABLE IF EXISTS `".$table."`;\n".$create['Create Table'].";\n\n");

            // Generated columns cannot be inserted; restore recomputes them
            $columns = $db->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 AND (EXTRA NOT LIKE '%GENERATED%' AND EXTRA NOT LIKE '%VIRTUAL%' AND EXTRA NOT LIKE '%PERSISTENT%' AND EXTRA NOT LIKE '%STORED%')
                 ORDER BY ORDINAL_POSITION",
                array($table)
            )->result_array();
            $names = array_column($columns, 'COLUMN_NAME');
            if (empty($names))
            {
                continue;
            }
            $col_sql = implode(', ', array_map(function ($n) { return '`'.str_replace('`', '``', $n).'`'; }, $names));
            $offset = 0;
            do
            {
                $rows = $db->query('SELECT '.$col_sql.' FROM `'.str_replace('`', '``', $table).'` LIMIT 500 OFFSET '.$offset)->result_array();
                if ( ! empty($rows))
                {
                    $values = array();
                    foreach ($rows as $row)
                    {
                        $values[] = '('.implode(', ', array_map(function ($v) use ($db) {
                            return $v === NULL ? 'NULL' : $db->escape((string) $v);
                        }, array_values($row))).')';
                    }
                    $w('INSERT INTO `'.$table.'` ('.$col_sql.") VALUES\n".implode(",\n", $values).";\n");
                }
                $offset += 500;
            }
            while (count($rows) === 500);
            $w("\n");
        }

        foreach ($views as $view)
        {
            $create = $db->query('SHOW CREATE VIEW `'.str_replace('`', '``', $view).'`')->row_array();
            $sql = preg_replace('/\sDEFINER=`[^`]*`@`[^`]*`/', '', $create['Create View']);
            $sql = preg_replace('/^CREATE\s+(ALGORITHM=\w+\s+)?/i', 'CREATE OR REPLACE $1', $sql);
            $w("DROP TABLE IF EXISTS `".$view."`;\nDROP VIEW IF EXISTS `".$view."`;\n".$sql.";\n\n");
        }
        $w("SET FOREIGN_KEY_CHECKS = 1;\n-- End of backup\n");
        gzclose($gz);
    }
}
