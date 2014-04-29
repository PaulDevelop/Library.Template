<?php

/**
 * TagType
 *
 * @package  Com\PaulDevelop\Library\Template
 * @category Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://opensource.org/licenses/MIT MIT
 */
namespace Com\PaulDevelop\Library\Template;

abstract class TagType
{
    #region const
    const NONE = 0;
    const OPEN = 1;
    const CLOSE = 2;
    const SINGLE = 3;
    #endregion
}
