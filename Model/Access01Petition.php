<?php

class Access01Petition extends AppModel {
  // Define class name for cake
  public $name = "Access01Petition";

  // Add behaviors
  public $actsAs = array(
    'Containable'
  );

  // Association rules from this model to other models
  public $belongsTo = array(
    "CoPetition"
  );

  public $hasMany = array();

  public $hasOne = array('AccessOrganization.AccessOrganization');

  // Validation rules for table elements
  public $validate = array(
    'co_petition_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'access_organization_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
  );
}
