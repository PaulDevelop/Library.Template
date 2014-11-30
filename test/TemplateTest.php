<?php

namespace Com\PaulDevelop\Library\Template;

use /** @noinspection PhpUndefinedClassInspection */
    PHPUnit_Framework_TestCase;

/** @noinspection PhpUndefinedClassInspection */
class TemplateTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function testProcessSimpleTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/simple.template.pdt');
        $this->assertEquals('Simple', trim($template->process()));
    }

    /**
     * @test
     */
    public function testProcessVariablesTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/variables.template.pdt');
        $template->bindVariable('variable', 'test');
        $this->assertEquals('Variables: test', trim($template->process()));
    }

    /**
     * @test
     */
    public function testProcessSetVariableTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/setVariable.template.pdt');
        $this->assertEquals('Variable: test', trim($template->process()));
    }

    /**
     * @test
     */
    public function testProcessIfTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/if.template.pdt');
        $template->bindVariable('variable', 42);
        $this->assertEquals("If-Branch", trim($template->process()));
    }

    /**
     * @test
     */
    public function testProcessForeachTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/foreach.template.pdt');
        $item1 = new \stdClass();
        $item1->name = 'Item 1';
        $item2 = new \stdClass();
        $item2->name = 'Item 2';
        $item3 = new \stdClass();
        $item3->name = 'Item 3';
        $list = array(
            $item1,
            $item2,
            $item3
        );
        $template->bindVariable('list', $list);

        //var_dump($template->process());
        //die;

        $this->assertEquals("Foreach:\n* Item 1\n* Item 2\n* Item 3", trim($template->process()));
        $this->assertEquals("Foreach:\n* Item 1\n* Item 2\n* Item 3\n", $template->process());
    }

    // embed

    // include

    // complex if-if-if, if-foreach-if, foreach-if-foreach, foreach-foreach-foreach nesting

    // function calls
    //   * in <pd:set name="..." value="%functionCall()% />
    //   * inline %functionCall()%

    // pattern handler
    /**
     * @test
     */
    public function testProcessScalarPatternHandlerTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/scalarPatternHandler.template.pdt');
        $template->registerPatternHandler('/%('.'foo'.')\:\/\/(.*?)%/', new ScalarFooStorage(), 'get');
        $this->assertEquals('bar', trim($template->process()));
    }

    /**
     * @test
     */
    public function testProcessArrayPatternHandlerTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/arrayPatternHandler.template.pdt');
        $template->registerPatternHandler('/%('.'foo'.')\:\/\/(.*?)%/', new ArrayFooStorage(), 'get');
        $this->assertEquals("Item 1\nItem 2\nItem 3", trim($template->process()));
    }

    // escape with backslash:
    //  * \<pd:set name=".." value="" /> should not get processed
    //  * backslash in attributes ... value="\"test\"" ...

    // <pd:if expression="%something% = 'This is a string.'">

    // %backslash%
    /**
     * @test
     */
    public function testProcessConstantBackslashTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/backslash.template.pdt');
        $template->bindVariable('variable', 'User');
        $this->assertEquals("Model\\User", trim($template->process()));
    }

    // %space%

    // %newline%
    /**
     * @test
     */
    public function testProcessConstantNewlineTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('project/test/_assets/templates/newline.template.pdt');
        ///*
        $item1 = new \stdClass();
        $item1->name = 'Item 1';
        $item2 = new \stdClass();
        $item2->name = 'Item 2';
        $item3 = new \stdClass();
        $item3->name = 'Item 3';
        $list = array(
            $item1,
            $item2,
            $item3
        );
        $template->bindVariable('list', $list);
        //*/
        //$template->bindVariable('variable', 'foo');

        //var_dump($template->process());
        //die;

        $this->assertEquals("Item 1,\nItem 2,\nItem 3", trim($template->process()));
    }
}
