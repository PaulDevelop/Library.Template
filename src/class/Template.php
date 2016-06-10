<?php

namespace Com\PaulDevelop\Library\Template;

use Com\PaulDevelop\Library\Common\Base;
use Com\PaulDevelop\Library\Common\ITemplate;
use Com\PaulDevelop\Library\Common\NodeCollection;

/**
 * Template
 *
 * @package  Com\PaulDevelop\Library\Template
 * @category Template
 * @author   Rüdiger Scheumann <code@pauldevelop.com>
 * @license  http://opensource.org/licenses/MIT MIT
 */
class Template extends Base implements ITemplate
{
    #region member
    /**
     * File name of template xml file.
     *
     * @var string
     */
    private $templateFileName;

    private $parentTemplateFileName;

    private $templateIncludePath;

    /**
     * Array of variable names and values.
     *
     * @var array
     */
    private $bindingVariables;

    private $patternHandler;

    private $functions;
    #endregion

    #region constructor
    /**
     * Constructor.
     *
     * @param string $templateFileName
     * @param string $parentTemplateFileName
     */
    public function __construct(
        $templateFileName = '',
        $parentTemplateFileName = ''
    ) {
        $this->templateFileName = $templateFileName;
        $this->parentTemplateFileName = $parentTemplateFileName;
        $this->templateIncludePath = '';
        $this->bindingVariables = array();
        $this->patternHandler = array();
        $this->functions = array();
    }
    #endregion

    #region methods
    public function registerPatternHandler(
        $pattern = '',
        &$invocant = null,
        $method = ''
    ) {
        array_push(
            $this->patternHandler,
            array('pattern' => $pattern, 'config' => array(&$invocant, $method))
        );
    }

    public function registerFunction($name = '', $function = null)
    {
        if (!array_key_exists($name, $this->functions)) {
            $this->functions[$name] = $function;
        }
    }

    public function registerCallback($name = '', $object = null, $function = '')
    {
        if (!array_key_exists($name, $this->functions)) {
            $this->functions[$name] = array($object, $function);
        }
    }

    public function registerCallbackObject($object = null)
    {
//        $class = get_class($object);
//        $rc = new \ReflectionClass($class);
        $rc = new \ReflectionClass($object);
        $methods = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
        /** @var \ReflectionMethod $method */
        foreach ($methods as $method) {
            $this->registerCallback($method->getName(), $object, $method->getName());
        }
    }

    /**
     * Bind template variable to content.
     *
     * @param string $variableName
     * @param mixed  $content
     * @param bool   $force
     */
    public function bindVariable($variableName = '', $content = null, $force = false)
    {
        if ($force || !array_key_exists($variableName, $this->bindingVariables)) {
            if (is_array($content)) {
                $this->bindArray('', $variableName, $content);
            } elseif (is_object($content)) {
                $this->bindObject('', $variableName, $content);
            } else { // assume scalar value
                $this->bindingVariables[$variableName] = $content;
            }
        }
    }

    private function bindArray($path = '', $variableName = '', $content = array())
    {
        if ($variableName != '') {
            $path .= ($path == '' ? '' : '.').$variableName;
        }

        for ($i = 0; $i < count($content); $i++) {
            if (is_array($content[$i])) {
                $this->bindArray($path.'['.$i.']', 'items', $content[$i]);
            } elseif (is_object($content[$i])) {
                $this->bindObject($path.'['.$i.']', '', $content[$i]);
            } else { // assume scalar value
                $this->bindingVariables[$path.'['.$i.'].value'] = $content[$i];

            }
        }
    }

    private function bindObject($path = '', $variableName = '', $content = null)
    {
        if ($variableName != '') {
            $path .= ($path != '' ? '.' : '').$variableName;
        }

        $properties = get_object_vars($content);
        foreach ($properties as $name => $property) {
            if (is_array($property)) {
                $this->bindArray($path.'.'.$name, '', $property);
            } elseif (is_object($property)) {
                $this->bindObject($path.'.'.$name, '', $property);
            } else { // assume scalar value
                $this->bindingVariables[$path.'.'.$name] = $property;
            }
        }
    }

    public function readVariable($variableName = '')
    {
        // init
        $result = '';

        // action
        if (array_key_exists($variableName, $this->bindingVariables)) {
//            if (is_array($content)) {
//                $this->bindArray('', $variableName, $content);
//            } elseif (is_object($content)) {
//                $this->bindObject('', $variableName, $content);
//            } else { // assume scalar value
//                $this->bindingVariables[$variableName] = $content;
//            }
            $result = $this->bindingVariables[$variableName];
        }

        // return
        return $result;
    }

    /**
     * Process template.
     *
     * @return string
     */
    public function process()
    {
        $parentScopeVariables = array();
        foreach ($this->bindingVariables as $key => $value) {
            $parentScopeVariables[$key] = $value;
        }

        // parse template file
        try {
            $tree = $this->buildNodeTree($this->parse($this->templateFileName));
        } catch (\Exception $e) {
            echo 'Error processing file '.$this->templateFileName.'...'.PHP_EOL;
            echo $e->getMessage();
        }

        // process parent template
        try {
            $parentTemplate = $tree->getNode('template.embed');
            $parentTemplateFileName = $parentTemplate['file']->Value;
            $parentTemplateFullFileName = dirname($this->templateFileName).DIRECTORY_SEPARATOR.$parentTemplateFileName;
            $parentTree = $this->buildNodeTree($this->parse($parentTemplateFullFileName));

            $parentTree->setNode('template.layout.content', $tree->getNode('template.layout')->Nodes);
            $tree = $parentTree;
        } catch (ChildDoesNotExistException $e) {
        }

        // process variables
        try {
            $variables = $tree->getNode('template.variables');
            foreach ($variables->Nodes as $sn) {
                if ($sn instanceof Tag) {
                    if ($sn->Name == 'variable') {
                        $variableName = $sn['name']->Value;
                        $variableContent = $sn->Nodes[0]->Content;
                        $this->registerVariable($variableName, $variableContent, $parentScopeVariables, array());
                    }
                }
            }
        } catch (ChildDoesNotExistException $e) {
        }

        // read layout
        $content = $this->processNodes($tree->getNode('template.layout'), $parentScopeVariables, 1)->Content;
        //echo "CONTENT".PHP_EOL;
        //var_dump($content);
        //die;
        $content = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $content); // remove BOMs
        $content = preg_replace('/\\\\%/', '%', $content); // remove \%
        $content = preg_replace('/\\\\</', '<', $content); // remove \<

        // replace constants
        ///*
        $content = preg_replace_callback(
            '/%constants\.(.*?)%/',
            function ($matches = array()) {
                // init
                $result = '';

                // action
                if ($matches[1] == 'backslash') {
                    $result = '\\';
                } else if ($matches[1] == 'space') {
                    $result = ' ';
                } else if ($matches[1] == 'newline') {
                    $result = PHP_EOL;
                }

                // return
                return $result;
            },
            $content
        );
        //*/

        //var_dump($content);
        //echo $content;
        //die;

        // return
        return $content;
    }

    private function buildNodeTree($nodeList = null)
    {
        // init
        $result = null;

        // action
        $parentNode = new Tag('pd', 'root', null, TagType::NONE, null);
        //$level = 0;
        foreach ($nodeList as $node) {
            if (get_class($node) == 'Com\PaulDevelop\Library\Template\Tag') {
                //$type = null;
                if ($node->Type == TagType::OPEN) {
                    //$type = 'open';
                    //$level++;
                    $parentNode->Nodes->Add($node);
                    $node->ParentNode = $parentNode;
                    $parentNode = $node;
                } elseif ($node->Type == TagType::CLOSE) {
                    //$type = 'close';
                    //$level--;
                    $parentNode = $parentNode->ParentNode;
                } elseif ($node->Type == TagType::SINGLE) {
                    //$type = 'single';
                    $parentNode->Nodes->Add($node);
                    $node->ParentNode = $parentNode;
                }
            } else {
                if (get_class($node) == 'Com\PaulDevelop\Library\Template\Text') {
                    $parentNode->Nodes->Add($node);
                    $node->ParentNode = $parentNode;
                }
            }
        }
        $result = $parentNode;

        // return
        return $result;
    }

    private function parse($fullFileName = '')
    {
        // init
        $result = new NodeCollection();
        $tagIsOpen = false;
        $currentTag = '';
        $currentText = '';

        $stringIsOpen = false;

        // action
        $content = implode('', file(realpath($fullFileName)));

        for ($i = 0; $i < strlen($content); $i++) {
            $prevChar = ($i > 0) ? substr($content, $i - 1, 1) : '';
            $currentChar = substr($content, $i, 1);

            if ($tagIsOpen) {
                if (!$stringIsOpen && $currentChar == Constants::TAG_END) { // tag close
                    $tagIsOpen = false;
                    //var_dump($currentTag);
                    $currentTag .= $currentChar; // add >

                    $tag = Tag::parse($currentTag, null);

                    // add to result
                    $result->Add($tag);
                    $currentTag = '';
                } else {
                    if ($prevChar != '\\' && $currentChar == Constants::STRING_TERMINATOR) {
                        $stringIsOpen = !$stringIsOpen;
                    }
                    $currentTag .= $currentChar;
                }
            } else {
                // tag open
                if ($prevChar != '\\' && $currentChar == Constants::TAG_START && $this->checkIfPdTag($content, $i)) {
                    $tagIsOpen = true;

                    $currentTag .= $currentChar; // add <

                    // add text node to result
                    //$addNewLine = false;

                    // find first \n
                    $pos = strpos($currentText, "\n");
                    if ($pos !== false && preg_match("/^[^\\S\n]*$/", substr($currentText, 0, $pos))) {

                        //var_dump($currentText);
                        //echo "yyy:".$pos.":".ord(substr($currentText, $pos, 1)).PHP_EOL;

                        $currentText = substr($currentText, $pos + 1);
                        //$addNewLine = true;
                        //var_dump($currentText);
                    }

                    // find last \n
                    $pos = strrpos($currentText, "\n");
                    if ($pos !== false && preg_match("/^[^\\S\\n]*$/", substr($currentText, $pos + 1))) {
                        //var_dump($currentText);
                        //echo "XXX:".$pos.":".ord(substr($currentText, $pos, 1)).PHP_EOL;
                        $currentText = substr($currentText, 0, $pos + 1);
                        //$currentText .= PHP_EOL;
                        //$addNewLine = true;
                        //var_dump($currentText);
                    }

                    //if ($addNewLine) {
                    //    $currentText .= PHP_EOL;
                    //}

                    // if only non-newline whitespace don't add
                    if (preg_match("/^[^\\S\\n]*$/", $currentText)) {
                        $currentText = '';
                    }


                    $text = new Text($currentText, null); // .PHP_EOL
                    $result->Add($text);
                    $currentText = '';
                } else {
                    $currentText .= $currentChar;
                }
            }
        }

        // return
        return $result;
    }

    private function checkIfPdTag($content, $i)
    {
        // init
        $result = false;

        // action
        $peek = substr($content, $i + 1, 3);
        if (substr($peek, 0, 1) == '/') {
            $peek = substr($content, $i + 2, 3);
        }
        if ($peek == 'pd:') {
            $result = true;
        }

        // return
        return $result;
    }

    private function registerVariable(
        $variableName,
        $variableContent,
        &$parentScopeVariables,
        $localScopeVariables
    ) {
        if (is_array($variableContent)) {
            $this->registerVariableArray($variableName, $variableContent, $parentScopeVariables, $localScopeVariables);
        } else {
            if (is_object($variableContent)) {
                $this->registerVariableObject(
                    $variableName,
                    $variableContent,
                    $parentScopeVariables,
                    $localScopeVariables
                );
            } else {
                $matches = array();
                $variableContent = (string)$variableContent;
                // %queryDb(%project://entity[@name=User]/property/*%)%
                //         <pd:set name="coreLanguage" value="%api://coreLanguage[@id=%request.pageParameter.id%]#count-1%" />

                //if (preg_match('/(?<!\\\\)((?:\\\\\\\\)*)%(.*?)%/', $variableContent, $matches)) {
                if (preg_match('/(?<!\\\\)((?:\\\\\\\\)*)%([a-z0-9-_\.]+\:\/\/.*?)%/', $variableContent, $matches)) {
                    //echo "PATTERN HANDLER".PHP_EOL;
                    //var_dump($this->patternHandler);
                    foreach ($this->patternHandler as $patternHandler) {
                        $pattern = $patternHandler['pattern'];
                        $config = $patternHandler['config'];

                        preg_match_all($pattern, $variableContent, $matches, PREG_SET_ORDER);
                        foreach ($matches as $matchSet) {
                            $path = $matchSet[2];
                            //echo $path.PHP_EOL;
                            // TODO: check, if path contains %variables%, already available in $this->_variables
                            $path = $this->pregReplaceCallback(
                                '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                                $path,
                                'Com\PaulDevelop\Library\Template\Template::processVariableString',
                                array_merge(
                                    $parentScopeVariables,
                                    $localScopeVariables
                                )
                            );
                            $obj = call_user_func($config, $path);
                            //var_dump($obj);

                            // TODO letzte aenderung: always clean up parentScopeVariables
                            foreach ($parentScopeVariables as $key2 => $value2) {
                                if (preg_match('/^('.$variableName.'\[.*)$/', $key2, $matches)) {
                                    // remove
                                    unset($parentScopeVariables[$matches[1]]);
                                }
                            }

                            // check, if depth is > 1
                            if ($obj == null) {
                                // throw exception
                            } else {
                                foreach ($parentScopeVariables as $key2 => $value2) {
                                    if (preg_match('/^('.$variableName.'\[.*)$/', $key2, $matches)) {
                                        // TODO letzte aenderung
                                        // //echo "REMOVE ".$variableName." (".$key2.") --- ".$matches[1]."<br />\n";
                                        // // remove
                                        // //var_dump($parentScopeVariables);
                                        // unset($parentScopeVariables[$matches[1]]);
                                        // echo $matches[1]."<br />\n";
                                        // //var_dump($parentScopeVariables);
                                        // //echo "REMOVE END<br />\n";
                                    }
                                }

                                if (is_array($obj) || is_object($obj)) {
                                    foreach ($obj as $key => $value) {
                                        if (is_object($value)) {
                                            foreach ($value as $subKey => $subValue) {
                                                $parentScopeVariables[$variableName.'['.$key.'].'.$subKey] =
                                                    (string)$subValue;
                                            }
                                        } else {
                                            $parentScopeVariables[$variableName.'.'.$key] = (string)$value;
                                        }
                                    }
                                } else {
                                    $parentScopeVariables[$variableName] = (string)$obj;
                                }
                            }
                        }
                    }
                } else {
                    $parentScopeVariables[$variableName] = $variableContent;
                }
            }
        }
    }

    private function registerVariableArray(
        $variableName,
        $variableContent,
        &$parentScopeVariables,
        $localScopeVariables
    ) {
        for ($i = 0; $i < count($variableContent); $i++) {
            if (is_array($variableContent[$i])) {
                $this->registerVariableArray(
                    $variableName.'['.$i.']',
                    $variableContent[$i],
                    $parentScopeVariables,
                    $localScopeVariables
                );
            } else {
                if (is_object($variableContent[$i])) {
                    $this->registerVariableObject(
                        $variableName.'['.$i.']',
                        $variableContent[$i],
                        $parentScopeVariables,
                        $localScopeVariables
                    );
                } else { // assume scalar value
                    $parentScopeVariables[$variableName.'['.$i.']'] = $variableContent[$i];
                }
            }
        }
    }

    private function registerVariableObject(
        $variableName,
        $variableContent,
        &$parentScopeVariables,
        $localScopeVariables
    ) {
        $properties = get_object_vars($variableContent);
        foreach ($properties as $name => $property) {
            if (is_array($property)) {
                $this->registerVariableArray(
                    $variableName.'.'.$name.'',
                    $variableContent->$name,
                    $parentScopeVariables,
                    $localScopeVariables
                );
            } else {
                if (is_object($property)) {
                    $this->registerVariableObject(
                        $variableName.'.'.$name.'',
                        $property,
                        $parentScopeVariables,
                        $localScopeVariables
                    );
                } else { // assume scalar value
                    $parentScopeVariables[$variableName.'.'.$name] = $property;
                }
            }
        }
    }

    private function pregReplaceCallback($pattern = '', $subject = '', $callback = '', $parameter = array())
    {
        // init
        //$result = '';

        // action
        preg_match_all($pattern, $subject, $matches);
        $result = $subject;
        $count = 0;
        foreach ($matches[2] as $match) {
            if ($callback == 'Com\PaulDevelop\Library\Template\Template::processVariableString') {
                $result = preg_replace(
                    '/(?<!\\\\)((?:\\\\\\\\)*)%'.str_replace('/', '\/', $match).'%/',
                    call_user_func($callback, $parameter, array(null, $match), $matches[1][$count]),
                    $result
                );
            } else {
                if ($callback == 'Com\PaulDevelop\Library\Template\Template::processFunctionCall') {
                    $result = preg_replace(
                    //'/(?<!\\\\)((?:\\\\\\\\)*)%'.str_replace('/', '\/', $match).'\('.$matches[3][$count].'\)%/',
                        '/(?<!\\\\)((?:\\\\\\\\)*)%'.str_replace('/', '\/', $match).'\('.str_replace('\\', '\\\\',
                            $matches[3][$count]).'\)%/',
                        call_user_func($callback, $parameter, array(null, $match), $matches[3][$count]),
                        $result
                    );
                }
            }
            $count++;
        }

        // return
        return $result;
    }

    /**
     * @param Tag   $node
     * @param array $parentScopeVariables
     * @param int   $depth
     *
     * @return Text
     */
    private function processNodes(
        Tag $node = null,
        $parentScopeVariables = array(),
        $depth = 0
    ) {
        // init
        $result = new Text();

        foreach ($node->Nodes as $childNode) {
            if ($childNode instanceof Text) {
                $content = $childNode->Content;
                $content = $this->pregReplaceCallback(
                    '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                    $content,
                    'Com\PaulDevelop\Library\Template\Template::processVariableString',
                    $parentScopeVariables
                );
                $content = $this->pregReplaceCallback(
                    '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.]*)\((.*?)\)%/i',
                    $content,
                    'Com\PaulDevelop\Library\Template\Template::processFunctionCall',
                    $parentScopeVariables
                );
                $result->Content .= $content;

            } elseif ($childNode->Name == 'if') {
                $localScopeVariables = array();
                $result->Content .= $this->processIfNode(
                    $childNode,
                    $parentScopeVariables,
                    $localScopeVariables,
                    $depth + 1
                )->Content;
            } elseif ($childNode->Name == 'foreach') {
                $localScopeVariables = array();
                $result->Content .= $this->processForeachNode(
                    $childNode,
                    $parentScopeVariables,
                    $localScopeVariables,
                    $depth + 1
                )->Content;
            } elseif ($childNode->Name == 'include') {
                $localScopeVariables = array();
                $result->Content .= $this->processIncludeNode(
                    $childNode,
                    $parentScopeVariables,
                    $localScopeVariables,
                    $depth + 1
                )->Content;
            } elseif ($childNode->Name == 'set') {
                $localScopeVariables = array();
                $this->processSetNode(
                    $childNode,
                    $parentScopeVariables,
                    $localScopeVariables
                );
            }
        }

        // return
        return $result;
    }

    private function processIfNode(
        Tag $ifNode = null,
        &$parentScopeVariables = array(),
        $localScopeVariables = array(),
        $depth = 0
    ) {
        // init
        $result = new Text();

        // action
        if ($ifNode != null
            && $ifNode->hasAttribute('expression')
        ) {
            // read expression
            $expression = $ifNode->getAttribute('expression')->Value;
            $possibilities = array();

            // exists always; may be overwritten
            $possibilities['__else__'] = array();
            $trueExpression = '__else__';

            $possibilities[$expression] = array();
            if ($this->check(array_merge($parentScopeVariables, $localScopeVariables), $expression) == true) {
                $trueExpression = $expression;
            }

            // get possibilities
            foreach ($ifNode->Nodes as $childNode) {
                if ($childNode instanceof Tag) {
                    if ($childNode->Name == 'elseif') {
                        $expression = $childNode->getAttribute('expression')->Value;
                        $check = $this->check(array_merge($parentScopeVariables, $localScopeVariables), $expression);
                        if ($check == true) {
                            if ($trueExpression == '__else__') { // NEW
                                $trueExpression = $expression;
                            }
                        }
                        $possibilities[$expression] = array();
                    } elseif ($childNode->Name == 'else') {
                        $expression = '__else__';
                        $possibilities[$expression] = array();
                    } elseif ($childNode->Name == 'if') {
                        array_push($possibilities[$expression], $childNode);
                    } elseif ($childNode->Name == 'foreach') {
                        array_push($possibilities[$expression], $childNode);
                    } elseif ($childNode->Name == 'include') { // new
                        array_push($possibilities[$expression], $childNode); // TODO: processInclude ?
                    } elseif ($childNode->Name == 'set') {
                        array_push($possibilities[$expression], $childNode);
                    }
                } else { // Text
                    array_push($possibilities[$expression], $childNode);
                }
            }

            // replace child nodes with new nodes
            $newNodes = new NodeCollection();
            foreach ($possibilities[$trueExpression] as $c) {
                $newNodes->Add($c);
            }

            // render text node variables
            foreach ($newNodes as $childNode) {
                if ($childNode instanceof Text) {
                    $content = $childNode->Content;
                    $content = $this->pregReplaceCallback(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                        $content,
                        'Com\PaulDevelop\Library\Template\Template::processVariableString',
                        array_merge($parentScopeVariables, $localScopeVariables)
                    );
                    $content = $this->pregReplaceCallback(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.]*)\((.*?)\)%/i',
                        $content,
                        'Com\PaulDevelop\Library\Template\Template::processFunctionCall',
                        array_merge($parentScopeVariables, $localScopeVariables)
                    );
                    $result->Content .= $content;
                } elseif ($childNode->Name == 'if') { // TODO: do I need this?
                    $result->Content .= $this->processIfNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables,
                        $depth + 1
                    )->Content;
                } elseif ($childNode->Name == 'foreach') { // TODO: do I need this?
                    $result->Content .= $this->processForeachNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables,
                        $depth + 1
                    )->Content;
                } elseif ($childNode->Name == 'include') {
                    $result->Content .= $this->processIncludeNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables,
                        $depth + 1
                    )->Content;
                } elseif ($childNode->Name == 'set') {
                    $this->processSetNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables
                    );
                }
            }
        }

        // return
        return $result;
    }

    /**
     * @param array  $scopeVariables
     * @param string $expression
     *
     * @return bool
     */
    private function check($scopeVariables = array(), $expression = '')
    {
        // init
        //$boolExpression = array();
        $boolOperator = array();

        // get boolean expessions
        $boolExpression = preg_split("/( AND )|( OR )/", $expression);

        // get boolean operator
        $offset = 0;
        while (true) {
            $posAnd = strpos($expression, "AND", $offset);
            $posOr = strpos($expression, "OR", $offset);
            if ($posAnd && $posOr) {
                if ($posAnd < $posOr) {
                    array_push($boolOperator, "AND");
                    $offset = $posAnd + 1;
                } else {
                    array_push($boolOperator, "OR");
                    $offset = $posOr + 1;
                }
            } elseif ($posAnd && !$posOr) {
                array_push($boolOperator, "AND");
                $offset = $posAnd + 1;
            } elseif (!$posAnd && $posOr) {
                array_push($boolOperator, "OR");
                $offset = $posOr + 1;
            } else {
                break;
            }
        } // while

        // evaluate
        $boolResult = false;
        if (count($boolExpression) > 0) {
            // take first boolean expression
            $boolResult = $this->checkAtom(
                $scopeVariables,
                array_shift($boolExpression)
            );
            for ($i = 0; $i < count($boolExpression); $i++) {
                if ($boolOperator[$i] == "AND") {
                    $boolResult = $boolResult && $this->checkAtom($scopeVariables, $boolExpression[$i]);
                } else {
                    $boolResult = $boolResult || $this->checkAtom($scopeVariables, $boolExpression[$i]);
                }
            }
        }

        // return
        return $boolResult;
    }

    public function checkAtom($scopeVariables, $expression)
    {
        $lhValue = '';
        $operator = '==';
        $rhValue = '';
        $chunks = preg_split("/ /", $expression);
        if (count($chunks) > 0) {
            $lhValue = $chunks[0];
        }
        if (count($chunks) > 1) {
            $operator = $chunks[1];
        }
        if (count($chunks) > 2) {
            $rhValue = $chunks[2];
        }

        $operator = html_entity_decode($operator);
        $lhValue = $this->pregReplaceCallback(
            '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
            $lhValue,
            'Com\PaulDevelop\Library\Template\Template::processVariableString',
            $scopeVariables
        );
        $rhValue = $this->pregReplaceCallback(
            '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
            $rhValue,
            'Com\PaulDevelop\Library\Template\Template::processVariableString',
            $scopeVariables
        );

        if ($operator == "==") {
            return ($lhValue == $rhValue);
        } elseif ($operator == "<") {
            return ((int)$lhValue < (int)$rhValue);
        } elseif ($operator == ">") {
            return ((int)$lhValue > (int)$rhValue);
        } elseif ($operator == "<=") {
            return ((int)$lhValue <= (int)$rhValue);
        } elseif ($operator == ">=") {
            return ((int)$lhValue >= (int)$rhValue);
        } elseif ($operator == "!=") {
            return ($lhValue != $rhValue);
        }

        return false;
    } // _checkAtom

    private function processForeachNode(
        Tag $foreachNode = null,
        &$parentScopeVariables = array(),
        $localScopeVariables = array(),
        $depth = 0
    ) {
        // init
        $result = new Text();

        // action
        if ($foreachNode != null
            && $foreachNode->hasAttribute('list')
            && $foreachNode->hasAttribute('item')
        ) {

            $list = $foreachNode->getAttribute('list')->Value;
            $item = $foreachNode->getAttribute('item')->Value;

            // check, if function name is in list attribute value
            if (preg_match(
                '/((?:_|[a-z])[a-z0-9-_\.]*)\((.*?)\)/msi',
                $list,
                $matches
            )
            ) {
                $functionName = $matches[1];
                $parameter = $matches[2];

                if (in_array(
                    $functionName,
                    get_class_methods('\Com\PaulDevelop\Library\Template\TemplateFunctions')
                )
                ) {
                    // check, if function is defined in class TemplateFunctions
                    $list = TemplateFunctions::$functionName(
                        $parameter
                    );
                } elseif (array_key_exists($functionName, $this->functions)) {
                    // check functions array
                    $list = call_user_func($this->functions[$functionName], $parameter, $parentScopeVariables, true);
                }
            }

            preg_match('/(?<!\\\\)((?:\\\\\\\\)*)%(.*?)%/', $list, $matches);
            $list = $this->processVariableArray(array_merge($parentScopeVariables, $localScopeVariables), $matches);

            if (count($list) > 0 && count($list[0]) == 0) {
                $chunks = preg_split(
                    '/,/',
                    $foreachNode->getAttribute('list')->Value
                );
                if (count($chunks) > 0) {
                    $list = array();
                    foreach ($chunks as $chunk) {
                        array_push($list, array('value' => $chunk));
                    }
                }
                if (count($list) > 0 && count($list[0]) == 0) {
                }
            }

            foreach ($list as $listItem) {
                $itemLocalScopeVariables = $localScopeVariables;
                $content = '';
                foreach ($foreachNode->Nodes as $childNode) {
                    if ($childNode instanceof Text) {
                        if (trim($childNode->Content) != '') {
                            foreach ($listItem as $key => $value) {
                                $variableName = $item;
                                $itemLocalScopeVariables[$variableName.'.'.$key] = (string)$value;
                            }

                            $content .= $childNode->Content;
                        }
                    } elseif ($childNode instanceof Tag) {
                        if ($childNode->Name == 'if') {
                            $ifLocalScopeVariables = array();
                            foreach ($itemLocalScopeVariables as $key => $value) {
                                $ifLocalScopeVariables[$key] = $value;
                            }
                            foreach ($listItem as $key => $value) {
                                $variableName = $item;
                                $ifLocalScopeVariables[$variableName.'.'.$key] = (string)$value;
                            }
                            $content .= $this->processIfNode(
                                $childNode,
                                $parentScopeVariables,
                                $ifLocalScopeVariables,
                                $depth + 1
                            )->Content;
                        } elseif ($childNode->Name == 'foreach') {
                            $foreachLocalScopeVariables = array();
                            foreach ($itemLocalScopeVariables as $key => $value) {
                                $foreachLocalScopeVariables[$key] = $value;
                            }
                            foreach ($listItem as $key => $value) {
                                $variableName = $item;
                                $foreachLocalScopeVariables[$variableName.'.'.$key] = (string)$value;
                            }
                            $content .= $this->processForeachNode(
                                $childNode,
                                $parentScopeVariables,
                                $foreachLocalScopeVariables,
                                $depth + 1
                            )->Content;
                        } elseif ($childNode->Name == 'include') {
                            $includeLocalScopeVariables = array();
                            foreach ($itemLocalScopeVariables as $key => $value) {
                                $includeLocalScopeVariables[$key] = $value;
                            }
                            foreach ($listItem as $key => $value) {
                                $variableName = $item;
                                $includeLocalScopeVariables[$variableName.'.'.$key] = (string)$value;
                            }
                            $content .= $this->processIncludeNode(
                                $childNode,
                                $parentScopeVariables,
                                $includeLocalScopeVariables,
                                $depth + 1
                            )->Content;
                        } elseif ($childNode->Name == 'set') {
                            foreach ($listItem as $key => $value) {
                                $variableName = $item;
                                $itemLocalScopeVariables[$variableName.'.'.$key] = (string)$value;
                            }
                            $this->processSetNode(
                                $childNode,
                                $parentScopeVariables,
                                $itemLocalScopeVariables
                            );
                        }
                    }

                    preg_match_all(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%('.$item.')\.(.*?)%/',
                        $foreachNode->ToString(),
                        $subMatches
                    );
                    for ($i = 0; $i < count($subMatches[0]); $i++) {
                        if (array_key_exists(
                            $subMatches[2][$i],
                            $listItem
                        )
                        ) {
                            $content = preg_replace(
                                '/(?<!\\\\)((?:\\\\\\\\)*)%'.$subMatches[1][$i].'\.'.$subMatches[2][$i].'%/',
                                $listItem[$subMatches[2][$i]],
                                $content
                            );
                        }
                    }

                    // process variables
                    $content = $this->pregReplaceCallback(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                        $content,
                        'Com\PaulDevelop\Library\Template\Template::processVariableString',
                        array_merge(
                            $parentScopeVariables,
                            $itemLocalScopeVariables
                        ),
                        ''
                    );
                    $content = $this->pregReplaceCallback(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.]*)\((.*?)\)%/i',
                        $content,
                        'Com\PaulDevelop\Library\Template\Template::processFunctionCall',
                        array_merge(
                            $parentScopeVariables,
                            $localScopeVariables
                        )
                    );
                }
                $result->Content .= $content;
            }
        }

        // return
        return $result;
    } // _check

    private function processVariableArray($scopeVariables, $matches)
    {
        // init
        $result = array();
        $variableName = $matches[2];

        $index = '0';
        $array = array();
        foreach ($scopeVariables as $name => $value) {
            if (preg_match("/^(".$variableName.")\[(.*?)\]\.(.*)$/", $name, $subMatches)) {
                if ($index != $subMatches[2]) {
                    $index = $subMatches[2];
                    array_push($result, $array);
                    $array = array();
                }

                $property = $subMatches[3];
                $array[$property] = $value;
            }
        }
        array_push(
            $result,
            $array
        );

        // return
        return $result;
    }

    private function processIncludeNode(
        Tag $includeNode = null,
        &$parentScopeVariables = array(),
        $localScopeVariables = array(),
        $depth = 0
    ) {
        // init
        $result = new Text();

        // action
        if ($includeNode != null
            && $includeNode->hasAttribute('file')
        ) {

            // read attribute "file", try to locate file in current directory
            $fileName = $includeNode->getAttribute('file')->Value;
            $fullFileName = dirname($this->templateFileName).DIRECTORY_SEPARATOR.$fileName;

            // try to locate file in template include path
            if (!file_exists($fullFileName) && $this->templateIncludePath != '') {
                $paths = preg_split(
                    '/\:/',
                    $this->templateIncludePath
                );
                foreach ($paths as $path) {
                    if (file_exists($path.$fileName)) {
                        $fullFileName = $path.$fileName;
                        break;
                    }
                }
            }

            // parse template file
            $tree = $this->buildNodeTree($this->parse($fullFileName));

            // process variables
            try {
                $variables = $tree->getNode('template.variables');
                foreach ($variables->Nodes as $sn) {
                    if ($sn instanceof Tag) {
                        if ($sn->Name == 'variable') {
                            $variableName = $sn['name']->Value;
                            $variableContent = $sn->Nodes[0]->Content;
                            $this->registerVariable(
                                $variableName,
                                $variableContent,
                                $parentScopeVariables,
                                array()
                            );
                        }
                    }
                }
            } catch (ChildDoesNotExistException $cdnee) {
                // do nothing
            }

            $layoutNode = $tree->getNode('template.layout');
            foreach ($layoutNode->Nodes as $childNode) {
                if ($childNode instanceof Text) {
                    $content = $childNode->Content;

                    // determine variable values
                    $content = $this->pregReplaceCallback(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                        $content,
                        'Com\PaulDevelop\Library\Template\Template::processVariableString',
                        array_merge($parentScopeVariables, $localScopeVariables)
                    );

                    // determine function call return values
                    $content = $this->pregReplaceCallback(
                        '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.]*)\((.*?)\)%/i',
                        $content,
                        'Com\PaulDevelop\Library\Template\Template::processFunctionCall',
                        array_merge($parentScopeVariables, $localScopeVariables)
                    );
                    $result->Content .= $content;
                } elseif ($childNode->Name == 'if') {
                    $result->Content .= $this->processIfNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables,
                        $depth + 1
                    )->Content;
                } elseif ($childNode->Name == 'foreach') {
                    $result->Content .= $this->processForeachNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables,
                        $depth + 1
                    )->Content;
                } elseif ($childNode->Name == 'include') {
                    $result->Content .= $this->processIncludeNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables,
                        $depth + 1
                    )->Content;
                } elseif ($childNode->Name == 'set') {
                    $this->processSetNode(
                        $childNode,
                        $parentScopeVariables,
                        $localScopeVariables
                    );
                }
            }
        }

        // return
        return $result;
    }

    private function processSetNode(
        Tag $setNode = null,
        &$parentScopeVariables = array(),
        $localScopeVariables = array()
    ) {
        // action
        if ($setNode != null
            && $setNode->hasAttribute('name')
            && $setNode->hasAttribute('value')
        ) {

            $name = $setNode->getAttribute('name')->Value;
            $value = $setNode->getAttribute('value')->Value;

            // process variables in value
            $name = $this->pregReplaceCallback(
                '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                $name,
                'Com\PaulDevelop\Library\Template\Template::processVariableString',
                array_merge($parentScopeVariables, $localScopeVariables)
            );
            //echo "VALUE".PHP_EOL;
            //var_dump($value);
            $value = $this->pregReplaceCallback(
                '/(?<!\\\\)((?:\\\\\\\\)*)%((?:_|[a-z])[a-z0-9-_\.\:]*)%/i',
                $value,
                'Com\PaulDevelop\Library\Template\Template::processVariableString',
                array_merge($parentScopeVariables, $localScopeVariables)
            );
            //var_dump($value);

            if (preg_match(
                '/((?:_|[a-z])[a-z0-9-_\.]*)\((.*?)\)/msi',
                $value,
                $matches
            )
            ) {
                $functionName = $matches[1];
                $parameter = $matches[2];
                if ($functionName == 'queryDb') {
                    $this->registerVariable(
                        $name,
                        $parameter,
                        $parentScopeVariables,
                        $localScopeVariables
                    );
                } elseif ($functionName == 'getArrayLength') {
                    $allParameters = array_merge(
                        $parentScopeVariables,
                        $localScopeVariables
                    );
                    $counter = 0;
                    $currentIndex = '';
                    foreach ($allParameters as $k => $v) {
                        if (preg_match(
                            '/^'.$parameter.'\[(.*?)\]/i',
                            $k,
                            $matches
                        )
                        ) {
                            if ($matches[1] != $currentIndex) {
                                $currentIndex = $matches[1];
                                $counter++;
                            }
                        }
                    }
                    $value = $counter;
                } elseif (in_array(
                    $functionName,
                    get_class_methods('\Com\PaulDevelop\Library\Template\TemplateFunctions')
                )
                ) { // check, if function is defined in class TemplateFunctions
                    $value = TemplateFunctions::$functionName(
                        $parameter
                    );
                } elseif (array_key_exists($functionName, $this->functions)) { // check functions array
                    $value = call_user_func($this->functions[$functionName], $parameter);
                }
            }
            $this->registerVariable($name, $value, $parentScopeVariables, $localScopeVariables);
            //var_dump($parentScopeVariables);
        }
    }

    public function setTemplateFileName($value = '')
    {
        $this->templateFileName = $value;
    }

    public function getTemplateFileName()
    {
        return $this->templateFileName;
    }

    public function getTemplateIncludePath()
    {
        return $this->templateIncludePath;
    }

    public function setTemplateIncludePath($value = '')
    {
        $this->templateIncludePath = $value;
    }

    private function convertToPlainObject($object = null)
    {
        // init
        $result = new \stdClass();

        // action
        if (get_class($object) == 'stdClass') {
            $result = $object;
        } elseif (is_a($object, '\Com\PaulDevelop\Library\Common\Base')) {
            foreach (get_class_methods(get_class($object)) as $method) {
                $matches = array();
                if (preg_match('/^get(.*)$/', $method, $matches)) {
                    $result->{lcfirst($matches[1])} = $object->{$matches[1]};
                }
            }
        }

        // return
        return $result;
    }

    private function processFunctionCall($scopeVariables = array(), $matches = array(), $parameter = array())
    {
        // init
        $result = '';

        // action
        $functionName = $matches[1];

        // check, if function is defined in class TemplateFunctions
//        if ($functionName == 'queryDb') {
//            $this->registerVariable(
//                $name,
//                $parameter,
//                $parentScopeVariables,
//                $localScopeVariables
//            );
//        } elseif ($functionName == 'getArrayLength') {
//            $allParameters = array_merge(
//                $parentScopeVariables,
//                $localScopeVariables
//            );
//            $counter = 0;
//            $currentIndex = '';
//            foreach ($allParameters as $k => $v) {
//                if (preg_match(
//                    '/^'.$parameter.'\[(.*?)\]/i',
//                    $k,
//                    $matches
//                )
//                ) {
//                    if ($matches[1] != $currentIndex) {
//                        $currentIndex = $matches[1];
//                        $counter++;
//                    }
//                }
//            }
//            $value = $counter;
//        } else
        if (in_array(
            $functionName,
            get_class_methods('\Com\PaulDevelop\Library\Template\TemplateFunctions')
        )
        ) {
            $result = TemplateFunctions::$functionName($parameter);
        } else { // check functions array
            if (array_key_exists($functionName, $this->functions)) {
                $result = call_user_func($this->functions[$functionName], $parameter);
            }
        }

        // return
        return $result;
    }

    private function processVariableString($scopeVariables, $matches, $escapeChars = '')
    {
        // init
        $result = '';

        //var_dump($escapeChars);

        // action
        if (count($matches) > 1) {
            $variableName = $matches[1];
            //if ($variableName == 'constants.backslash') {
            //    $result = '\\';
            if (preg_match('/^constants\./', $variableName)) {
                $result = '%'.$variableName.'%';
                //echo 'pVS'.PHP_EOL;
            } elseif (array_key_exists($variableName, $scopeVariables)) {
                $result = (string)$scopeVariables[$variableName];
            } else {
                $result = '';
            }
        }
        $result = $escapeChars.$result;
        $result = addcslashes($result, '$');

        // return
        return $result;
    }
    #endregion
}
