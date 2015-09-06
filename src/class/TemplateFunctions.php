<?php

namespace Com\PaulDevelop\Library\Template;

/**
 * Class TemplateFunctions
 *
 * @category Template
 * @package  Com.PaulDevelop.Library.Data.Processing.Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://www.ruediger-scheumann.de/ PL
 * @version  SVN: $Id$
 * @link     <none>
 */
abstract class TemplateFunctions
{
    public static function subString($data = '')
    {
        // init
        $result = '';
        // action
        $chunks = preg_split('/,/', $data);
        if (count($chunks) == 2) {
            $result = substr($chunks[0], $chunks[1]);
        } else {
            if (count($chunks) == 3) {
                $result = substr($chunks[0], $chunks[1], $chunks[2]);
            }
        }

        return $result;
    }

    public static function increment($data = '')
    {
        $value = ((int)$data) + 1;
        return $value;
    }

    public static function modulo($data = '')
    {
        $chunks = preg_split('/,/', $data);
        if (count($chunks) > 1) {
            $value = (int)$chunks[0] % (int)$chunks[1];
        }
        return $value;
    }

    public static function add($data = '')
    {
        $value = 0;
        $chunks = preg_split('/,/', $data);
        if (count($chunks) > 1) {
            $value = (int)$chunks[0] + (int)$chunks[1];
        }
        return $value;
    }

    public static function lowerCaseFirst($data = '')
    {
        $value = strtolower(substr($data, 0, 1)).substr($data, 1);
        return $value;
    }

    public static function lowerCase($data = '')
    {
        $value = strtolower($data);
        return $value;
    }

    public static function upperCaseFirst($data = '')
    {
        return ucfirst($data);
    }

    public static function upperCase($data = '')
    {
        $value = strtoupper($data);
        return $value;
    }

    public static function concat($data = '')
    {
        $value = '';
        $chunks = preg_split('/,/', $data);
        foreach ($chunks as $chunk) {
            $value .= $chunk;
        }
        return $value;
    }

    public static function replace($data = '')
    {
        $value = '';
        $chunks = preg_split('/,/', $data);
        if (count($chunks) == 3) {
            $value = str_replace($chunks[1], $chunks[2], $chunks[0]);
        }
        return $value;
    }

    public function test()
    {
        return "test";
    }
}
