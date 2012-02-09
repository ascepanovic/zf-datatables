<?php

class Users_Form_EditUser extends Users_Form_AddUser {
    public function init ()
    {
        parent::init();
        
        $entryForm = $this->getDisplayGroup('entrydata');
        $entryForm->setLegend('Edit User');
        
        $hidden = $this->createElement('hidden', $this->_attribs['idColumn']);
        $hidden->setDecorators(array('ViewHelper'));
        $entryForm->addElement($hidden);
    }
}