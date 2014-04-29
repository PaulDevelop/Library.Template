<?php

namespace Com\PaulDevelop\Library\Template;

//include_once('Com.PaulDevelop.Library/src/class/Base.class.php');
//include_once('phar://Com.PaulDevelop.Library.phar/class/Base.class.php');
use Com\PaulDevelop\Library\Common\Base;

/**
 * Attribute
 *
 * @package  Com\PaulDevelop\Library\Template
 * @category Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @property string $Key
 * @property string $Value
 */
class Attribute extends Base
{
    #region member
    private $key;
    private $value;
    #endregion

    #region constructor
    public function __construct($key = '', $value = '')
    {
        $this->key = $key;
        $this->value = $value;
    }
    #endregion

    #region methods
    /**
     * Parse raw string containing attribute.
     *
     * @param string $rawAttribute
     *
     * @return Attribute
     */
    public static function parse($rawAttribute = '')
    {
        // init
        $result = null;

        // action
        $pos = strpos($rawAttribute, '=');
        $key = substr($rawAttribute, 0, $pos);
        $value = substr($rawAttribute, $pos + 1);
        $result = new Attribute($key, $value);

        // return
        return $result;
    }

    public function toString()
    {
        return $this->key.'="'.$this->value.'"';
    }
    #endregion

    #region properties
    public function getKey()
    {
        return $this->key;
    }

    public function getValue()
    {
        return $this->value;
    }
    #endregion
}
