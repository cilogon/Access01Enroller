<?php
  $params = array();
  $params['title'] = _txt('pl.access01_enroller.title');

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
 print $this->Form->hidden('access_organization_id');

 // Unlock the field so we can manipulate with JavaScript and not trigger the security code.
 $this->Form->unlockField('Access01Petition.access_organization_id');
?> 
<script>
  $(function() {
    $("#organization-choose").autocomplete({
      source: "<?php print $this->Html->url(array('plugin' => 'access_organization', 'controller' => 'access_organizations', 'action' => 'find', 'co' => $cur_co['Co']['id'])); ?>",
      minLength: 3,
      select: function (event, ui) {
        $("#organization-choose").hide();
        $("#organization-choose-name").text(ui.item.label).show();
        $("#Access01PetitionAccessOrganizationId").val(ui.item.value);
        $("#organization-choose-button").prop('disabled', false).focus();
        $("#organization-choose-clear-button").show();
        return false;
      },
      search: function (event, ui) {
        $("#organization-choose-search-container .co-loading-mini").show();
      },
      focus: function (event, ui) {
        $("#organization-choose-search-container .co-loading-mini").hide();
      },
      close: function (event, ui) {
        $("#organization-choose-search-container .co-loading-mini").hide();
      }
    });

    $("#organization-choose-button").click(function(e) {
      displaySpinner();
      e.preventDefault();
      $("#Access01PetitionPetitionerAttributesForm").submit();
    });

    $("#organization-choose-clear-button").click(function() {
      stopSpinner();
      $("#organization-choose-name").hide();
      $("#Access01PetitionAccessOrganizationId").val("");
      $("#organization-choose-button").prop('disabled', true).focus();
      $("#organization-choose-clear-button").hide();
      $("#organization-choose").val("").show().focus();
      return false;
    });

    $("#organization-choose-other-link").click(function(e) {
      $("#Access01PetitionAccessOrganizationId").val(<?php print $vv_access_organization_other_id; ?>);
      displaySpinner();
      e.preventDefault();
      $("#Access01PetitionPetitionerAttributesForm").submit();
    });

    $('[data-toggle="tooltip"]').tooltip();

  });

</script>

<h2>Your registration is not complete yet</h2>

<p>
You must select your primary home organization and verify
your email address before your registration is complete.
</p>

<p>
Type in the box below to find and select your primary home organization.
</p>

<div id="organization-choose-search-container">
  <label for="organization-choose" class="col-form-label-sm">Primary Home Organization: </label>
  <span class="co-loading-mini-input-container">
    <input id="organization-choose" type="text" class="form-control-sm" placeholder="enter organization name" />
    <span class="co-loading-mini"><span></span><span></span><span></span></span>
  </span>
  <span id="organization-choose-name" style="display: none;"></span>
  <button id="organization-choose-button" class="btn btn-primary btn-sm" disabled="disabled"><?php print _txt('Select'); ?></button>
  <button id="organization-choose-clear-button" class="btn btn-sm" style="display: none;"><?php print _txt('op.clear'); ?></button>
</div>

<div id="organization-choose-other">
  <p>
  Can't find your organization? <a id="organization-choose-other-link" href="">Click here</a>
  to select 'Other' and ACCESS staff will follow up later with you.
  </p>
</div>

<?php print $this->Form->end();
