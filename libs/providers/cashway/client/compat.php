<?php
/**
 * Compatibibility stuff, when needed.
*/

/**
 * getallheaders() was not available
 * for CLI before 5.5.7 and FastCGI before 5.4.
 * This may bite a few users, so aliasing it here if necessary.
 *
 * @link https://secure.php.net/manual/fr/function.getallheaders.php
*/
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }
}
