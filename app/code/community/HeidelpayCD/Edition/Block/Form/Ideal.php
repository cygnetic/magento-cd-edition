<?php
class HeidelpayCD_Edition_Block_Form_Ideal extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('hcd/form/ideal.phtml');
    }
}
