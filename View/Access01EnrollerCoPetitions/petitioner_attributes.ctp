<?php

  $params = array();
  $params['title'] = _txt('pl.access01_enroller.title');

  print $this->element("pageTitleAndButtons", $params);

  print $this->Form->create(
    'Access01Enroller.Access01Petition',
    array(
      'inputDefaults' => array(
        'label' => false,
        'div' => false
      )
    )
  );

 print $this->Form->hidden('co_petition_id', array('default' => $co_petition_id));
 print $this->Form->hidden('co_enrollment_flow_wedge_id', array('default' => $vv_efwid));

?> 
<ul id="access01_petition_1" class="fields form-list">
  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('access_organization_id', _txt('pl.access01_enroller.organization')); ?><span class="required">*</span>
      </div>
      <div class="field-desc">
        <?php print _txt('pl.access01_enroller.organization.desc'); ?>
      </div>
    </div>
    <div class="field-info">
      <?php 
        $args = array();
        $args['empty'] = true;
        print $this->Form->select('access_organization_id', $vv_access_organizations, $args);
      ?>
    </div>
  </li>
  <li class="fields-submit">
    <div class="field-name">
      <span class="required"><?php print _txt('fd.req'); ?></span>
    </div>
    <div class="field-info">
      <?php print $this->Form->submit(_txt('pl.access01_enroller.button.label.submit')); ?>
    </div>
  </li>
</ul>

<?php
  print $this->Form->end();
