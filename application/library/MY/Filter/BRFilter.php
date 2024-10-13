<?php
namespace MY\Filter;

class BrFilter extends \MY\Filter_Abstract {

    public function filterInit()
    {
        $this->params['macro'] = TRUE;
    }

    public function display(&$names)
    {
        echo $this->view();
    }

    public function view()
    {
        return  '<br/><br/>';
    }
}
