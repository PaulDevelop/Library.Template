<?php

namespace Com\PaulDevelop\Library\Template;

    //include_once('Com.PaulDevelop.Library/src/class/GenericCollection.class.php');
    //include_once('phar://Com.PaulDevelop.Library.phar/class/GenericCollection.class.php');
//include_once('Attribute.class.php');
use Com\PaulDevelop\Library\Common\GenericCollection;

/**
 * AttributeCollection
 *
 * @package  Com\PaulDevelop\Library\Template
 * @category Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://opensource.org/licenses/MIT MIT
 */
class AttributeCollection extends GenericCollection
{
    #region constructor
    public function __construct()
    {
        parent::__construct('Com\PaulDevelop\Library\Template\Attribute');
    }
    #endregion

    #region methods
    /**
     * Parse raw string containing attributes.
     *
     * @param string $rawAttributes
     *
     * @return AttributeCollection
     */
    public static function parse($rawAttributes = '')
    {
        // init
        $result = new AttributeCollection();

        // action
        $rawAttributes = trim($rawAttributes);
        $keyIsOpen = true;
        $valueIsOpen = false;
        $key = '';
        $value = '';
        for ($i = 0; $i < strlen($rawAttributes); $i++) {
            $prevChar = ($i > 0) ? substr($rawAttributes, $i - 1, 1) : '';
            $currentChar = substr($rawAttributes, $i, 1);

            if ($keyIsOpen) {
                if ($currentChar == '=') {
                    $keyIsOpen = false;
                } else {
                    $key .= $currentChar;
                }
            } else {
                if ($prevChar != '\\' && $currentChar == '"') {
                    if ($valueIsOpen) {
                        $key = trim($key);
                        $value = trim($value);
                        $attribute = Attribute::Parse($key.'='.$value);
                        $result->Add($attribute, $key);

                        // reset
                        $key = '';
                        $value = '';
                        $keyIsOpen = true;
                        $valueIsOpen = false;
                    } else {
                        $valueIsOpen = true;
                    }
                } else {
                    if ($prevChar == '\\') {
                        $value = substr($value, 0, strlen($value) - 1);
                    }
                    $value .= $currentChar;
                }
            }
        }

        // return
        return $result;
    }

    public function toString()
    {
        // init
        $result = '';

        // action
        foreach ($this->collection as $attribute) {
            /* @var $attribute Attribute */
            $result .= (empty($result) ? '' : ' ').$attribute->ToString();
        }

        // return
        return $result;
    }
    #endregion
}
