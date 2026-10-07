<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dompdf wrapper (bundled in application/third_party/dompdf - no Composer).
 * Remote resources, PHP and JavaScript inside documents are disabled; images are embedded as data URIs.
 */
class Pdf_renderer
{
    /**
     * @param string $paper A4 | A5 | ...
     */
    public function render(string $html, string $paper = 'A4', string $orientation = 'portrait', string $title = ''): string
    {
        require_once APPPATH.'third_party/dompdf/autoload.inc.php';

        $cache = STORAGEPATH.'cache'.DIRECTORY_SEPARATOR.'dompdf';
        if ( ! is_dir($cache))
        {
            @mkdir($cache, 0750, TRUE);
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', FALSE);
        $options->set('isPhpEnabled', FALSE);
        $options->set('isJavascriptEnabled', FALSE);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('fontCache', $cache);
        $options->set('tempDir', $cache);
        $options->set('chroot', array(APPPATH.'third_party/dompdf'));

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        if ($title !== '')
        {
            $dompdf->addInfo('Title', $title);
        }
        $dompdf->addInfo('Author', association('association_name'));
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * The association logo as a data URI ('' when missing or not embeddable without GD).
     */
    public function logo_data_uri(): string
    {
        $path = association('logo_path');
        if ($path === '' || strpos($path, '..') !== FALSE)
        {
            return '';
        }
        $file = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if ( ! is_file($file))
        {
            return '';
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file);
        if ( ! in_array($mime, array('image/jpeg', 'image/png'), TRUE))
        {
            return '';
        }
        // PNG needs the GD extension inside Dompdf; JPEG does not
        if ($mime === 'image/png' && ! extension_loaded('gd'))
        {
            return '';
        }
        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }

    /**
     * Stream PDF bytes to the browser and stop.
     */
    public function send(string $bytes, string $filename, bool $inline = FALSE): void
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        header('Content-Type: application/pdf');
        header('Content-Length: '.strlen($bytes));
        header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $bytes;
        exit;
    }
}
