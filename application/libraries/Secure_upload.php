<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Validated file uploads stored OUTSIDE the web root (storage/uploads/<folder>).
 *
 * - Extension allow-list and size limit from config/app.php.
 * - Real MIME type checked with finfo (the browser-supplied type is ignored).
 * - Random file name; the original name is returned only for display/download.
 * - Files are served back through a controller that checks permissions (send()).
 */
class Secure_upload
{
    /** @var array<string, string[]> extension => accepted MIME types */
    private $mime_map = array(
        'pdf'  => array('application/pdf'),
        'jpg'  => array('image/jpeg', 'image/pjpeg'),
        'jpeg' => array('image/jpeg', 'image/pjpeg'),
        'png'  => array('image/png'),
        'csv'  => array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'),
        'txt'  => array('text/plain'),
    );

    /**
     * Store one uploaded file.
     *
     * @param string $field       $_FILES key
     * @param string $folder      Sub-folder of storage/uploads (e.g. 'tenants')
     * @param string $types       Pipe list, e.g. 'pdf|jpg|jpeg|png'
     * @return array{success: bool, message: string, path?: string, original_name?: string}
     *         path is relative to STORAGEPATH, e.g. "uploads/tenants/2026/10/ab12...pdf".
     *         When no file was chosen: success TRUE with no path.
     */
    public function store(string $field, string $folder, string $types, int $max_kb): array
    {
        if ( ! isset($_FILES[$field]) || ! is_array($_FILES[$field]) || (int) $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE)
        {
            return array('success' => TRUE, 'message' => '');
        }
        $file = $_FILES[$field];

        if (is_array($file['error']))
        {
            return array('success' => FALSE, 'message' => 'Upload one file only.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            $too_big = in_array((int) $file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), TRUE);
            return array('success' => FALSE, 'message' => $too_big ? 'The file is too large (max '.$this->size_label($max_kb).').' : 'The file could not be uploaded. Please try again.');
        }
        if ( ! is_uploaded_file($file['tmp_name']))
        {
            return array('success' => FALSE, 'message' => 'Invalid upload.');
        }
        if ((int) $file['size'] > $max_kb * 1024)
        {
            return array('success' => FALSE, 'message' => 'The file is too large (max '.$this->size_label($max_kb).').');
        }
        if ((int) $file['size'] === 0)
        {
            return array('success' => FALSE, 'message' => 'The file is empty.');
        }

        $original = $this->clean_name((string) $file['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = explode('|', strtolower($types));
        if ($ext === '' || ! in_array($ext, $allowed, TRUE) || ! isset($this->mime_map[$ext]))
        {
            return array('success' => FALSE, 'message' => 'Only '.strtoupper(implode(', ', $allowed)).' files are allowed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if ( ! in_array($mime, $this->mime_map[$ext], TRUE))
        {
            return array('success' => FALSE, 'message' => 'The file content does not match its type (.'.$ext.').');
        }

        $relative_dir = 'uploads/'.$folder.'/'.date('Y').'/'.date('m');
        $absolute_dir = STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $relative_dir);
        if ( ! is_dir($absolute_dir) && ! mkdir($absolute_dir, 0750, TRUE) && ! is_dir($absolute_dir))
        {
            log_message('error', 'Secure_upload: cannot create '.$absolute_dir);
            return array('success' => FALSE, 'message' => 'The file could not be saved (storage folder not writable).');
        }

        $name = bin2hex(random_bytes(16)).'.'.$ext;
        if ( ! move_uploaded_file($file['tmp_name'], $absolute_dir.DIRECTORY_SEPARATOR.$name))
        {
            log_message('error', 'Secure_upload: move failed for '.$name);
            return array('success' => FALSE, 'message' => 'The file could not be saved.');
        }

        return array('success' => TRUE, 'message' => '', 'path' => $relative_dir.'/'.$name, 'original_name' => $original);
    }

    /**
     * Delete a stored file (relative path from store()). Silently ignores missing files.
     */
    public function delete(?string $relative_path): void
    {
        $absolute = $this->absolute($relative_path);
        if ($absolute !== NULL && is_file($absolute))
        {
            @unlink($absolute);
        }
    }

    /**
     * Stream a stored file to the browser and stop.
     */
    public function send(?string $relative_path, string $download_name, bool $inline = TRUE): void
    {
        $absolute = $this->absolute($relative_path);
        if ($absolute === NULL || ! is_file($absolute))
        {
            show_404();
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($absolute);
        $safe_name = $this->clean_name($download_name);

        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($absolute));
        header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.$safe_name.'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($absolute);
        exit;
    }

    /**
     * Resolve a stored relative path, refusing anything outside storage/uploads.
     */
    private function absolute(?string $relative_path): ?string
    {
        if ($relative_path === NULL || $relative_path === '' || strpos($relative_path, '..') !== FALSE
            || ! preg_match('#^uploads/[a-z0-9_]+/\d{4}/\d{2}/[a-f0-9]{32}\.[a-z0-9]{2,4}$#', $relative_path))
        {
            return NULL;
        }
        return STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    }

    private function clean_name(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
        return mb_substr(trim((string) $name), 0, 200) ?: 'file';
    }

    private function size_label(int $kb): string
    {
        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : $kb.' KB';
    }
}
