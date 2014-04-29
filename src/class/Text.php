<?php

namespace Com\PaulDevelop\Library\Template;

    //include_once('Com.PaulDevelop.Library/src/class/Base.class.php');
    //include_once('phar://Com.PaulDevelop.Library.phar/class/GenericCollection.class.php');
    //include_once('INode.interface.php');
//include_once('NodeCollection.class.php');

use Com\PaulDevelop\Library\Common\Base;
use Com\PaulDevelop\Library\Common\INode;
use Com\PaulDevelop\Library\Common\NodeCollection;

/**
 * Text
 *
 * @package  Com\PaulDevelop\Library\Template
 * @category Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @property $Content
 * @property $ParentNode
 * @property $Nodes
 */
class Text extends Base implements INode
{
    #region member
    private $content;
    private $parentNode;
    #endregion

    #region constructor
    public function __construct($content = '', $parentNode = null)
    {
        $this->content = $content;
        $this->parentNode = $parentNode;
    }
    #endregion

    #region methods
    /**
     * Convert to string.
     *
     * @return string
     */
    public function toString()
    {
        return $this->content;
    }
    #endregion

    #region properties

    /**
     * Get content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set content.
     *
     * @param string $value
     */
    public function setContent($value = '')
    {
        $this->content = $value;
    }

    /**
     * Get parent node.
     *
     * @return INode
     */
    public function getParentNode()
    {
        return $this->parentNode;
    }

    /**
     * Set parent node.
     *
     * @param INode $value
     */
    public function setParentNode(INode $value = null)
    {
        $this->parentNode = $value;
    }

    /**
     * Get nodes.
     *
     * @return NodeCollection
     */
    public function getNodes()
    {
        return new NodeCollection();
    }
    #endregion
}
