<?php

class Users_Form_AddUser extends Zend_Form {
    public function init ()
    {
        $this->addAttribs(array(
            'method' => 'post',
            'accept-charset' => 'UTF-8',
        ));
        
        $this->setDecorators(array(
            'FormElements',
            'Form'
        ));
                    
        $model = new Users_Model_DbTable_Users();
        $db = $model->getAdapter();

        $this->setMethod('post');

        $listOptions = array(1=>'Yes',0=>'No');
		$element = new Zend_Form_Element_Select('active', array(
			'label'        => 'Active:',
		    'multiOptions' => $listOptions,
			'required'     => true,
			'validators'   => array(
                    array('InArray',
                          false,
                          array(array_keys($listOptions))))
		));
		$this->addElement($element);

        $element = new Zend_Form_Element_Text('email');
        $element->setLabel('Email:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid email address."); //zend_form
        $this->addElement($element);

        $element = new Zend_Form_Element_Text('firstName');
        $element->setLabel('First Name:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid first name."); //zend_form
        $this->addElement($element);
        
        $element = new Zend_Form_Element_Text('lastName');
        $element->setLabel('Last Name:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid last name. Use only alphanumeric characters."); //zend_form
        $this->addElement($element);

        $element = new Zend_Form_Element_Text('address');
        $element->setLabel('Address:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid address."); //zend_form
        $this->addElement($element);

        $element = new Zend_Form_Element_Text('city');
        $element->setLabel('City:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid city."); //zend_form
        $this->addElement($element);
                
        $element = new Zend_Form_Element_Text('state');
        $element->setLabel('State:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid state."); //zend_form
        $this->addElement($element);

        $element = new Zend_Form_Element_Text('zip');
        $element->setLabel('Zip:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid zip."); //zend_form
        $this->addElement($element);

        $element = new Zend_Form_Element_Text('phone');
        $element->setLabel('Phone:') //zend_form
                 ->setRequired(true) //zend_form
                 ->addErrorMessage("Please enter a valid phone."); //zend_form
        $this->addElement($element);
                
                
        $this->addElement(
            'Submit',
            'submit',
            array(
                'required'   => false,
                'ignore'     => true,
                'label'      => 'Submit',
                'style'    => 'margin-top:1em;',
                'class'    =>'fg-button ui-state-default ui-priority-primary ui-corner-all',
                'decorators' => array(
                    'ViewHelper',
                ),
            )
        );

        $this->addElement(new Zend_Form_Element_Submit(
            'goback',
            array(
                'label'    => 'Cancel',
                'required' => false,
                'ignore'   => true,
                'style'    => 'margin-top:1em;',
                'class'    =>'fg-button ui-state-default ui-priority-primary ui-corner-all submitButton',
                'decorators' => array(
                    'ViewHelper',
                ),
            )
        ));

        $legend = 'Add New User';

        $this->addDisplayGroup(
            array('active','email','firstName','lastName','address','city','state','zip','phone','submit', 'goback','id'), 'entrydata',
            array(
                'class'=>'entryForm',
//                'disableLoadDefaultDecorators' => true,
//                'decorators' => $this->_standardGroupDecorator,
                'legend' => $legend
            )
        );
    }
}