<?php
defined('ROOTPATH') OR exit('No direct script access allowed');

/**
 * Minimal .env loader (no Composer dependency).
 *
 * - Reads KEY=VALUE lines from the project root .env file.
 * - Ignores blank lines and lines starting with '#'.
 * - Supports single/double quoted values and inline comments after unquoted values.
 * - Real environment variables (set by Apache SetEnv / Nginx fastcgi_param) win over .env.
 *
 * Usage: env('DB_HOST', 'localhost')
 */
final class Env
{
    /** @var array<string, string> */
    private static $values = array();

    /** @var bool */
    private static $loaded = FALSE;

    public static function load(string $file): void
    {
        if (self::$loaded)
        {
            return;
        }
        self::$loaded = TRUE;

        if ( ! is_file($file) || ! is_readable($file))
        {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === FALSE)
        {
            return;
        }

        foreach ($lines as $line)
        {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === FALSE)
            {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if ( ! preg_match('/^[A-Z][A-Z0-9_]*$/', $key))
            {
                continue;
            }

            self::$values[$key] = self::clean($value);
        }
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = NULL)
    {
        $real = getenv($key);
        if ($real !== FALSE)
        {
            return self::cast($real);
        }

        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]))
        {
            return self::cast($_SERVER[$key]);
        }

        if (array_key_exists($key, self::$values))
        {
            return self::cast(self::$values[$key]);
        }

        return $default;
    }

    private static function clean(string $value): string
    {
        if ($value === '')
        {
            return '';
        }

        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote && strlen($value) >= 2)
        {
            $value = substr($value, 1, -1);
            return $quote === '"' ? str_replace(array('\\n', '\\"'), array("\n", '"'), $value) : $value;
        }

        // Strip inline comment: VALUE  # comment
        $hash = strpos($value, ' #');
        if ($hash !== FALSE)
        {
            $value = rtrim(substr($value, 0, $hash));
        }

        return $value;
    }

    /**
     * @return mixed
     */
    private static function cast(string $value)
    {
        switch (strtolower($value))
        {
            case 'true':
                return TRUE;
            case 'false':
                return FALSE;
            case 'null':
                return NULL;
            default:
                return $value;
        }
    }
}

if ( ! function_exists('env'))
{
    /**
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = NULL)
    {
        return Env::get($key, $default);
    }
}
