<?php

namespace Com\PaulDevelop\Library\Application;

use Com\PaulDevelop\Library\Template\Template;

class TemplateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function testParseSimpleTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('test/_assets/templates/simple.template.pdt');
        $this->assertEquals('Simple', trim($template->process()));
    }

    /**
     * @test
     */
    public function testParseVariablesTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('test/_assets/templates/variables.template.pdt');
        $template->bindVariable('variable', 'test');
        $this->assertEquals('Variables: test', trim($template->process()));
    }

    /**
     * @test
     */
    public function testParseSetVariableTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('test/_assets/templates/setVariable.template.pdt');
        $this->assertEquals('Variable: test', trim($template->process()));
    }

    /**
     * @test
     */
    public function testParseIfTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('test/_assets/templates/if.template.pdt');
        $template->bindVariable('variable', 42);
        $this->assertEquals("If-Branch", trim($template->process()));
    }

    /**
     * @test
     */
    public function testParseForeachTemplate()
    {
        $template = new Template();
        $template->setTemplateFileName('test/_assets/templates/foreach.template.pdt');
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

    // escape with backslash:
    //  * \<pd:set name=".." value="" /> should not get processed
    //  * backslash in attributes ... value="\"test\"" ...
}
