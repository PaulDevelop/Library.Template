<?php

namespace Com\PaulDevelop\Library\Template;

//include_once('phar://Com.PaulDevelop.Library.phar/class/Base.class.php');
//include_once('INode.interface.php');
//include_once('TagType.class.php');
//include_once('AttributeCollection.class.php');
//include_once('AttributeCollection.php');
use Com\PaulDevelop\Library\Common\Base;
use Com\PaulDevelop\Library\Common\INode;
use Com\PaulDevelop\Library\Common\NodeCollection;

/**
 * Tag
 *
 * @package  Com\PaulDevelop\Library\Template
 * @category Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @property string                     $Name
 * @property string                     $Namespace
 * @property NodeCollection             $Nodes
 * @property INode                      $ParentNode
 * @property TagType                    $Type
 * @property AttributeCollection        $Attributes
 */
class Tag extends Base implements INode, \arrayaccess
{
    #region member
    private $namespace;
    private $name;
    private $attributes;
    private $type;
    private $parentNode;
    private $nodes;
    #endregion

    #region constructor
    public function __construct(
        $namespace = '',
        $name = '',
        $attributes = null,
        $type = TagType::NONE,
        $parentNode = null,
        $nodes = null
    ) {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->attributes = ($attributes == null) ? new AttributeCollection() : $attributes;
        $this->type = $type;
        $this->parentNode = $parentNode;
        $this->nodes = ($nodes == null) ? new NodeCollection() : $nodes;
    }
    #endregion

    #region methods
    /**
     * Parse raw string containing tag.
     *
     * @param string $rawTag
     * @param INode  $parentNode
     *
     * @return Tag
     */
    public static function parse($rawTag = '', $parentNode = null)
    {
        // init
        $result = null;

        // action
        $namespace = '';
        $name = '';
        $tagNameIsOpen = true;
        $rawAttributes = '';

//        var_dump($rawTag);
//        var_dump(substr($rawTag, 0, 1));
//        var_dump(substr($rawTag, strlen($rawTag) - 1, 1));

        // remove <
        if (substr($rawTag, 0, 1) == Constants::TAG_START) {
            $rawTag = substr($rawTag, 1);
        }

        // remove >
        if (substr($rawTag, strlen($rawTag) - 1, 1) == Constants::TAG_END) {
            $rawTag = substr($rawTag, 0, strlen($rawTag) - 1);
        }

//        var_dump($rawTag);

        //$type = TagType::NONE;
        $type = null;
        if (substr($rawTag, 0, 1) == '/') {
            $type = TagType::CLOSE;
            $rawTag = substr($rawTag, 1);
        } elseif (substr($rawTag, strlen($rawTag) - 1, 1) == '/') {
            $type = TagType::SINGLE;
            $rawTag = substr($rawTag, 0, strlen($rawTag) - 1);
        } else {
            $type = TagType::OPEN;
        }

        for ($i = 0; $i < strlen($rawTag); $i++) {
            $currentChar = substr($rawTag, $i, 1);
            if ($tagNameIsOpen) {
                if ($currentChar == ' ') {
                    $tagNameIsOpen = false;
                } elseif ($i == strlen($rawTag) - 1) {
                    $name .= $currentChar;
                    $tagNameIsOpen = false;
                } else {
                    $name .= $currentChar;
                }
            } else { // attributes
                $rawAttributes .= $currentChar;
            }
        }

        if ($pos = strpos($name, ':')) {
            $namespace = substr($name, 0, $pos);
            $name = substr($name, $pos + 1);
        }

        $attributes = AttributeCollection::parse($rawAttributes);
        $result = new Tag($namespace, $name, $attributes, $type, $parentNode, null);

        // return
        return $result;
    }

    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->attributes[$offset]) ? $this->attributes[$offset] : null;
    }

    /**
     * HasAttribute.
     *
     * @param string $attributeName
     *
     * @return bool
     */
    public function hasAttribute($attributeName)
    {
        // init
        $result = false;

        // action
        foreach ($this->attributes as $attribute) {
            if ($attribute->Key == $attributeName) {
                $result = true;
                break;
            }
        }

        // result
        return $result;
    }

    /**
     * GetAttribute.
     *
     * @param string $attributeName
     *
     * @return Attribute
     */
    public function getAttribute($attributeName)
    {
        // init
        $result = null;

        // action
        foreach ($this->attributes as $attribute) {
            if ($attribute->Key == $attributeName) {
                $result = $attribute;
                break;
            }
        }

        // result
        return $result;
    }

    public function setNode($path = '', $nodes = null)
    {
        // get node to be set
        $curObj = $this->getNode($path);

        // replace pd:content node with with nodes from template to be embedded

        // make a deep copy of nodes list
        $parentNodes = unserialize(
            serialize($curObj->ParentNode->Nodes)
        ); // TODO: quick fix instead implementing clone methods for deep copies

        // clear nodes list
        $curObj->ParentNode->Nodes->Clear();

        // add new nodes to nodes list
        foreach ($parentNodes as $parentNodeItem) {
            if (get_class($parentNodeItem) == 'Com\PaulDevelop\Library\Template\Tag'
                //&& $node->Name == 'content' ) { // replace pd:content-node with nodes from template to be embedded
                && $parentNodeItem->Name == $curObj->Name
                && $parentNodeItem->Namespace == $curObj->Namespace
            ) { // replace pd:content-node with nodes from template to be embedded
                foreach ($nodes as $node) {
                    $curObj->ParentNode->Nodes->add($node);
                }
            } else { // copy other nodes (other than pd:content) to nodes list
                $curObj->ParentNode->Nodes->add($parentNodeItem);
            }
        }
    }

    /**
     * Get node.
     *
     * @param string $path Path
     *
     * @throws ChildDoesNotExistException
     *
     * @return Tag
     */
    public function getNode($path = '')
    {
        // init
        $chunks = $this->splitPath($path);

        // action
        $curObj = $this;
        foreach ($chunks as $chunk) {
            // check, if attributes exist
            $regs = array();
            preg_match("/^([a-z]+)(?:\[(.*)\])?$/i", $chunk, $regs);

            // if there are attributes, store them in array
            $chunkAttributes = array();
            if (count($regs) > 2) {
                // get chunk name
                $chunk = $regs[1];

                // get attributes
                $tmpAttributes = preg_split("/\,/", $regs[2]);
                for ($i = 0; $i < count($tmpAttributes); $i++) {
                    list($key, $value) = preg_split("/\=/", $tmpAttributes[$i]); // split into key = value
                    $key = substr($key, 1, strlen($key) - 1); // remove @
                    $value = trim($value, '\''); // remove ''
                    $chunkAttributes[$key] = $value; // add to attributes list
                }
            }

            if (($curObj = $curObj->getChildNode($chunk, $chunkAttributes)) != null) {
                // zuweisung schon oben in if-block schon erfolgt
            } else {
                throw new ChildDoesNotExistException('Child node "'.$chunk.'" '.' ("'.$path.'") does not exist.');
            }
        }

        // return
        return $curObj;
    }

    private function splitPath($path)
    {
        // init
        $textDelimiter = '\'';
        $pathDelimiter = '.';
        $result = array();

        // action
        $stringIsOpen = false;
        $currentChunk = '';
        for ($i = 0; $i < strlen($path); $i++) {
            $currentSymbol = $path[$i];

            if ($currentSymbol == $textDelimiter) {
                $stringIsOpen = !$stringIsOpen;
            }

            if ($currentSymbol == $pathDelimiter && !$stringIsOpen) {
                $result[count($result)] = $currentChunk;
                $currentChunk = '';
                continue;
            }
            $currentChunk .= $currentSymbol;
        }

        $result[count($result)] = $currentChunk;

        // return
        return $result;
    }

    /**
     * Get child node.
     *
     * @param string $nodeName       Node name
     * @param array  $nodeAttributes Node attributes
     *
     * @throws MultipleChildrenFoundException
     *
     * @return mixed
     */
    public function getChildNode($nodeName = "", $nodeAttributes = array())
    {
        // init
        $obj = null;
        $count = 0;

        // action
        foreach ($this->Nodes as $node) {
            if (gettype($node) == 'object'
                && get_class($node) == 'Com\PaulDevelop\Library\Template\Tag'
            ) {
                if ($node->Name == $nodeName) {
                    // if found child node with good name, check attributes as well
                    $allAttributesAreOK = true;
                    foreach ($nodeAttributes as $key => $value) {
                        if ($node[$key]->Value != $value) {
                            $allAttributesAreOK = false;
                            break;
                        }
                    }

                    if ($allAttributesAreOK == true) {
                        $count++;
                        if ($count > 1) {
                            throw new MultipleChildrenFoundException(
                                'Found multiple children with name "'.$nodeName.'".'
                            );
                        }
                        $obj = $node;
                    }
                }
            }
        }

        // return
        return $obj;
    }

    /**
     * @param string $nodeName
     * @param array  $nodeAttributes
     *
     * @return array
     * @throws MultipleChildrenFoundException
     */
    public function getChildNode2($nodeName = "", $nodeAttributes = array())
    {
        // init
        $obj = null;
        $count = 0;

        $index = 0;

        // action
        //foreach ( $this->Nodes as $node ) {
        for ($i = 0; $i < $this->Nodes->Count; $i++) {
            if (gettype($this->Nodes[$i]) == 'object'
                && get_class($this->Nodes[$i]) == 'Com\PaulDevelop\Library\Template\Tag'
            ) {
                if ($this->Nodes[$i]->Name == $nodeName) {
                    // if found child node with good name, check attributes as well
                    $allAttributesAreOK = true;
                    foreach ($nodeAttributes as $key => $value) {
                        if ($this->Nodes[$i][$key]->Value != $value) {
                            $allAttributesAreOK = false;
                            break;
                        }
                    }

                    if ($allAttributesAreOK == true) {
                        $count++;
                        if ($count > 1) {
                            throw new MultipleChildrenFoundException(
                                'Found multiple children with name "'.$nodeName.'".'
                            );
                        }
                        $obj = & $this->Nodes[$i];
                        $index = $i;
                        //print_r($obj);
                    }
                }
            }
        }

        // return
        return array($obj, $index);
    }

    /**
     * Convert to string.
     *
     * @return string
     */
    public function toString()
    {
        // init
        $result = '';

        // action
        //if ( $this->_nodes->Count == 0 ) {
        //  $result .= '<'.$this->_namespace.':'.$this->_name;

        //  // add attributes
        //  $result .= ' '.$this->_attributes->ToString();

        //  $result .= ' />';
        //}
        //else {
        $result .= '<'.$this->namespace.':'.$this->name;

        // add attributes
        $result .= ' '.$this->attributes->toString().'>';

        // add child nodes
        foreach ($this->nodes as $childNode) {
            //if ( $childNode instanceof Text ) {
            //  $result .= $childNode->Content;
            //}
            //else if ( $childNode instanceof Tag) {
            /* @var $childNode INode */
            $result .= $childNode->toString();
            //}
        }

        $result .= '</'.$this->namespace.':'.$this->name.'>';
        //}

        // return
        return $result;
    }
    #endregion

    #region properties
    /**
     * Get namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function setName($value = '')
    {
        $this->name = $value;
    }

    /**
     * Get attributes.
     *
     * @return AttributeCollection
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get type.
     *
     * @return TagType
     */
    public function getType()
    {
        return $this->type;
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
        return $this->nodes;
    }
    #endregion
}
