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
     * The association logo as a data URI ('' when it cannot be used).
     *
     * $for_pdf = FALSE (screen preview / browser print): JPEG and PNG are always embedded.
     * $for_pdf = TRUE (Dompdf): a PNG needs the GD extension inside Dompdf, so without GD it is
     * left out of the PDF. Every case that drops the logo is logged so it is never silent.
     */
    public function logo_data_uri(bool $for_pdf = TRUE): string
    {
        $status = $this->logo_status();
        if ($status['problem'] !== NULL && ($for_pdf || $status['mime'] === NULL))
        {
            static $logged = array();
            if ( ! isset($logged[$status['problem']]))
            {
                $logged[$status['problem']] = TRUE;
                log_message('error', 'Association logo not shown'.($for_pdf ? ' in PDF' : '').': '.$status['problem']);
            }
            return '';
        }
        return 'data:'.$status['mime'].';base64,'.base64_encode((string) file_get_contents($status['file']));
    }

    /**
     * Where the configured logo stands (used by logo_data_uri() and System Check).
     *
     * @return array{file: string, mime: ?string, problem: ?string} problem = why the PDF cannot show it
     */
    public function logo_status(): array
    {
        $path = association('logo_path');
        if ($path === '' || strpos($path, '..') !== FALSE)
        {
            return array('file' => '', 'mime' => NULL, 'problem' => 'no logo is configured (Settings -> Association)');
        }
        $file = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if ( ! is_file($file) || ! is_readable($file))
        {
            return array('file' => $file, 'mime' => NULL, 'problem' => 'the logo file public/'.$path.' is missing or not readable');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file);
        if ( ! in_array($mime, array('image/jpeg', 'image/png'), TRUE))
        {
            return array('file' => $file, 'mime' => NULL, 'problem' => 'public/'.$path.' is not a JPG or PNG image');
        }
        if ($mime === 'image/png' && ! extension_loaded('gd'))
        {
            return array('file' => $file, 'mime' => $mime, 'problem' => 'the logo is a PNG and the PHP gd extension is not enabled (use a JPG logo or enable gd)');
        }
        return array('file' => $file, 'mime' => $mime, 'problem' => NULL);
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
