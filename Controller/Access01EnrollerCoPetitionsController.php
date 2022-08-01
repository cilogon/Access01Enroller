<?php

// This COmanage Registry enrollment plugin is intended to be used
// with a self-signup enrollment flow for ACCESS that requires
// authentication with a federated identity to begin the flow.
//
// The following enrollment steps are implemented:
//
// finalize:
//   - Used to add OrgIdentity with ACCESS CI ePPN as
//     login identifier.
//
// petitionerAttributes:
//   - Used to collect the ACCESS Organization for the
//     user from a controlled set.
//
// provision:
//   - Used to ask if the user wants to set a Kerberos
//     password for their new ACCESS ID and if so
//     sets the password in the KDC.
//
// start:
//   - Used to detect if the ACCESS IdP was used for 
//     authentication and redirect, or if the email 
//     asserted by the IdP is already known and attached
//     to a registered user.
//
// TODO Ask user if want to add other email addresses?

App::uses('Access01Petition', 'Access01Enroller.Model');
App::uses('AccessOrganization', 'AccessOrganization.Model');
App::uses('CoPetitionsController', 'Controller');
App::uses('HtmlHelper', 'View/Helper');
App::uses('Krb', 'KrbAuthenticator.Model');
App::uses('KrbAuthenticator', 'KrbAuthenticator.Model');
 
class Access01EnrollerCoPetitionsController extends CoPetitionsController {
  // Class name, used by Cake
  public $name = "Access01EnrollerCoPetitions";
  public $uses = array("CoPetition");

  /**
   * Plugin functionality following finalize step:
   *
   * @param Integer $id CO Petition ID
   * @param Array $onFinish URL, in Cake format
   */
  protected function execute_plugin_finalize($id, $onFinish) {
    $args = array();
    $args['conditions']['CoPetition.id'] = $id;
    $args['contain']['CoEnrollmentFlow'] = 'CoEnrollmentFlowFinMessageTemplate';
    $args['contain']['EnrolleeCoPerson'] = array('PrimaryName', 'Identifier');
    $args['contain']['EnrolleeCoPerson']['CoGroupMember'] = 'CoGroup';
    $args['contain']['EnrolleeCoPerson']['CoPersonRole'][] = 'Cou';
    $args['contain']['EnrolleeCoPerson']['CoPersonRole']['SponsorCoPerson'][] = 'PrimaryName';
    $args['contain']['EnrolleeOrgIdentity'] = array('EmailAddress', 'PrimaryName');

    $petition = $this->CoPetition->find('first', $args);
    $this->log("Access01Enroller Finalize: Petition is " . print_r($petition, true));

    $coId = $petition['CoPetition']['co_id'];
    $this->log("FOO coId is " . print_r($petition['CoPetition']['co_id'], true));
    $this->log("FOO coId is $coId");
    $coPersonId = $petition['CoPetition']['enrollee_co_person_id'];

    // Find the ACCESS ID.
    $accessId = null;
    foreach($petition['EnrolleeCoPerson']['Identifier'] as $i) {
      if($i['type'] == 'accessid') {
        $accessId = $i['identifier'];
      }
    }

    if(!empty($accessId)) {
      try {
        // Create the OrgIdentity.

        // Begin a transaction.
        $dataSource = $this->CoPetition->getDataSource();
        $dataSource->begin();
      
        // TODO put this all in a transaction and properly
        // check for errors when saving.
        $this->CoPetition->EnrolleeOrgIdentity->clear();

        $data = array();
        $data['EnrolleeOrgIdentity']['co_id'] = $coId;

        if(!$this->CoPetition->EnrolleeOrgIdentity->save($data)) {
          $msg = "ERROR could not create OrgIdentity: ";
          $msg = $msg . "ACCESS ID $accessId and CoPerson ID $coPersonId: ";
          $msg = $msg . "Validation errors: ";
          $msg = $msg . print_r($this->CoPetition->EnrolleeOrgIdentity->validationErrors, true);
          $this->log($msg);
          $dataSource->rollback();
          throw new RuntimeException($msg);
        }
        
        $orgIdentityId = $this->CoPetition->EnrolleeOrgIdentity->id;

        // Link the CoPerson to the OrgIdentity.
        $this->CoPetition->EnrolleeCoPerson->CoOrgIdentityLink->clear();

        $data = array();
        $data['CoOrgIdentityLink']['co_person_id'] = $coPersonId;
        $data['CoOrgIdentityLink']['org_identity_id'] = $orgIdentityId;

        if(!$this->CoPetition->EnrolleeCoPerson->CoOrgIdentityLink->save($data)) {
          $msg = "ERROR could not create CoOrgIdentityLink: ";
          $msg = $msg . "ACCESS ID $accessId and CoPerson ID $coPersonId: ";
          $msg = $msg . "Validation errors: ";
          $msg = $msg . print_r($this->CoPetition->EnrolleeCoPerson->CoOrgIdentityLink->validationErrors, true);
          $this->log($msg);
          $dataSource->rollback();
          throw new RuntimeException($msg);
        }

        // Attach a Name to the OrgIdentity.
        $this->CoPetition->EnrolleeOrgIdentity->Name->clear();

        $data = array();
        $data['Name']['given'] = $petition['EnrolleeCoPerson']['PrimaryName']['given'];
        $data['Name']['family'] = $petition['EnrolleeCoPerson']['PrimaryName']['family'];
        if(!empty($petition['EnrolleeCoPerson']['PrimaryName']['middle'])) {
          $data['Name']['middle'] = $petition['EnrolleeCoPerson']['PrimaryName']['middle'];
        }
        $data['Name']['type'] = NameEnum::Official;
        $data['Name']['org_identity_id'] = $orgIdentityId;
        $data['Name']['primary_name'] = true;

        if(!$this->CoPetition->EnrolleeOrgIdentity->Name->save($data)) {
          $msg = "ERROR could not create Name: ";
          $msg = $msg . "ACCESS ID $accessId and CoPerson ID $coPersonId: ";
          $msg = $msg . "Validation errors: ";
          $msg = $msg . print_r($this->CoPetition->EnrolleeOrgIdentity->Name->validationErrors, true);
          $this->log($msg);
          $dataSource->rollback();
          throw new RuntimeException($msg);
        }

        // Attach an Identifier of type EPPN to the OrgIdentity and
        // mark it as a login identifier.
        $this->CoPetition->EnrolleeOrgIdentity->Identifier->clear();

        $data = array();
        $data['Identifier']['identifier'] = $accessId . '@access-ci.org';
        $data['Identifier']['type'] = IdentifierEnum::ePPN;
        $data['Identifier']['status'] = SuspendableStatusEnum::Active;
        $data['Identifier']['login'] = true;
        $data['Identifier']['org_identity_id'] = $orgIdentityId;

        if(!$this->CoPetition->EnrolleeOrgIdentity->Identifier->save($data)) {
          $msg = "ERROR could not create Identifier: ";
          $msg = $msg . "ACCESS ID $accessId and CoPerson ID $coPersonId: ";
          $msg = $msg . "Validation errors: ";
          $msg = $msg . print_r($this->CoPetition->EnrolleeOrgIdentity->Identifier->validationErrors, true);
          $this->log($msg);
          $dataSource->rollback();
          throw new RuntimeException($msg);
        }

        // Commit the transaction.
        $dataSource->commit();
      } catch (Exception $e) {
        // We want to keep the enrollment flow going even if unable
        // to create the OrgIdentity so just continue.
      }
    }

    // This step is completed so redirect to continue the flow.
    $this->redirect($onFinish);
  }

  /**
   * Plugin functionality following petitionerAttributes step
   *
   * @param Integer $id CO Petition ID
   * @param Array $onFinish URL, in Cake format
   */
   
  protected function execute_plugin_petitionerAttributes($id, $onFinish) {
    $args = array();
    $args['conditions']['CoPetition.id'] = $id;
    $args['contain']['EnrolleeCoPerson']['CoOrgIdentityLink'] = 'OrgIdentity';
    $args['contain']['EnrolleeCoPerson'][] = 'Name';
    $args['contain']['EnrolleeCoPerson'][] = 'Identifier';

    $petition = $this->CoPetition->find('first', $args);
    $this->log("Petitioner Attributes: Petition is " . print_r($petition, true));

    $coId = $petition['CoPetition']['co_id'];
    $coPersonId = $petition['CoPetition']['enrollee_co_person_id'];
    $coPersonRoleId = $petition['CoPetition']['enrollee_co_person_role_id'];

    // Set the CoPetition ID to use as a hidden form element.
    $this->set('co_petition_id', $id);

    // Save the onFinish URL to which we must redirect after receiving
    // the incoming POST data.
    if(!$this->Session->check('access01.plugin.petitionerAttributes.onFinish')) {
      $this->Session->write('access01.plugin.petitionerAttributes.onFinish', $onFinish);
    }

    // Create an instance of the AccessOrganization model since we do 
    // not have a direct relationship with it.
    $accessOrganizationModel = new AccessOrganization();

    // Pull the list of ACCESS Organizations for drop down in view.

    $args = array();
    $args['conditions']['AccessOrganization.status'] = AccessOrganizationStatusEnum::Active;
    $args['contain'] = false;

    $accessOrganizations = $accessOrganizationModel->find('all', $args);

    $vv_access_organizations = array();
    foreach($accessOrganizations as $a) {
      $vv_access_organizations[$a['AccessOrganization']['id']] = $a['AccessOrganization']['name'];
    }

    asort($vv_access_organizations);
    $this->set('vv_access_organizations', $vv_access_organizations);

    // Process incoming POST data.
    if($this->request->is('post')) {
      // Validate incoming data.
      $data = $this->validatePost();

      if(!$data) {
        // The call to validatePost() sets $this->Flash if there are any validation
        // errors so just return after resetting the enrollment flow wedge id.

        $this->set('vv_efwid', $this->data['Access01Petition']['co_enrollment_flow_wedge_id']);
        return;
      }

      // Save the Access01 petition data.
      $petitionModel = new Access01Petition();
      $petitionModel->clear();

      $petitionData = array();
      $petitionData['Access01Petition']['co_petition_id'] = $this->data['Access01Petition']['co_petition_id'];
      $petitionData['Access01Petition']['access_organization_id'] = $this->data['Access01Petition']['access_organization_id'];

      if(!$petitionModel->save($petitionData)) {
        $this->log("Error saving Access01Petition data " . print_r($petitionData, true));
        $this->Flash->set(_txt('pl.access01_enroller.error.access01petition.save'), array('key' => 'error'));
        $this->redirect("/");
      }

      // Set the organization on the CO Person Role.
      $args = array();
      $args['conditions']['AccessOrganization.id'] = $this->data['Access01Petition']['access_organization_id'];
      $args['contain'] = false;

      $accessOrganization = $accessOrganizationModel->find('first', $args);
      $accessOrganizationName = $accessOrganization['AccessOrganization']['name'];

      $this->CoPetition->EnrolleeCoPersonRole->id = $coPersonRoleId;
      $this->CoPetition->EnrolleeCoPersonRole->saveField('o', $accessOrganizationName);

      $onFinish = $this->Session->consume('access01.plugin.petitionerAttributes.onFinish');

      // Done processing all POST data so redirect to continue enrollment flow.
      $this->redirect($onFinish);
    } // End of POST.

    // GET fall through to the view.
  }

  /**
   * Plugin functionality following provision step
   *
   * @param Integer $id CO Petition ID
   * @param Array $onFinish URL, in Cake format
   */
   
  protected function execute_plugin_provision($id, $onFinish) {
    $args = array();
    $args['conditions']['CoPetition.id'] = $id;

    $args['contain']['EnrolleeCoPerson']['CoOrgIdentityLink'] = 'OrgIdentity';
    $args['contain']['EnrolleeCoPerson'][] = 'Name';
    $args['contain']['EnrolleeCoPerson'][] = 'Identifier';

    $petition = $this->CoPetition->find('first', $args);
    $this->log("Provision: Petition is " . print_r($petition, true));

    $coId = $petition['CoPetition']['co_id'];
    $coPersonId = $petition['CoPetition']['enrollee_co_person_id'];

    // Find the ACCESS ID.
    $accessId = null;
    foreach($petition['EnrolleeCoPerson']['Identifier'] as $i) {
      if($i['type'] == 'accessid') {
        $accessId = $i['identifier'];
      }
    }

    // Find the OrgIdentity used for authentication.
    $authenticatingOrgIdentity = null;
    foreach($petition['EnrolleeCoPerson']['CoOrgIdentityLink'] as $link) {
      if($link['OrgIdentity']['status'] == OrgIdentityStatusEnum::Synced &&
         !empty($link['OrgIdentity']['o'])) {
        $authenticatingOrgIdentity = $link['OrgIdentity'];
      }
    }

    // We assume that the CO has one and only one instantiated KrbAuthenticator
    // plugin and it is used for ACCESS ID password management.
    $args = array();
    $args['conditions']['Authenticator.co_id'] = $coId;
    $args['conditions']['Authenticator.plugin'] = 'KrbAuthenticator';
    $args['contain'] = false;

    $authenticator = $this->CoPetition->Co->Authenticator->find('first', $args);

    $args = array();
    $args['conditions']['KrbAuthenticator.authenticator_id'] = $authenticator['Authenticator']['id'];
    $args['contain'] = false;

    $krbAuthenticatorModel = new KrbAuthenticator();

    $krbAuthenticator = $krbAuthenticatorModel->find('first', $args);

    $cfg = array();
    $cfg['Authenticator'] = $authenticator['Authenticator'];
    $cfg['KrbAuthenticator'] = $krbAuthenticator['KrbAuthenticator'];
    $krbAuthenticatorModel->setConfig($cfg);

    // Set the CoPetition ID to use as a hidden form element.
    $this->set('co_petition_id', $id);

    $this->set('vv_authenticator', $krbAuthenticator);
    $this->set('vv_co_person_id', $coPersonId);
    $this->set('vv_access_id', $accessId);
    $this->set('vv_login_server', $authenticatingOrgIdentity['o']);

    // Save the onFinish URL to which we must redirect after receiving
    // the incoming POST data.
    if(!$this->Session->check('access01.plugin.provision.onFinish')) {
      $this->Session->write('access01.plugin.provision.onFinish', $onFinish);
    }

    // Process incoming POST data.
    if($this->request->is('post')) {
      // If both password inputs are empty just continue.
      if(empty($this->data['Krb']['password']) &&
         empty($this->data['Krb']['password2'])) {

        $onFinish = $this->Session->consume('access01.plugin.provision.onFinish');
        $this->redirect($onFinish);
      }

      try {
        $krbAuthenticatorModel->manage($this->data, $coPersonId, true);
        $onFinish = $this->Session->consume('access01.plugin.provision.onFinish');
        $this->redirect($onFinish);
      } catch (Exception $e) {
        // Fall through to display the form again.
        $this->Flash->set($e->getMessage(), array('key' => 'error'));
      }
    } // POST
    
    // GET fall through to view.
  }

  /**
   * Plugin functionality following start step
   *
   * @param Integer $id CO Petition ID
   * @param Array $onFinish URL, in Cake format
   */
   
  protected function execute_plugin_start($id, $onFinish) {
    // This plugin assumes the authentication flow requires 
    // authentication and so at this point we can examine
    // CGI environment variables asserted by the upstream
    // identity provider.

    // Do not allow the enrollment flow to continue if
    // the user authenticated using the ACCESS IdP since
    // the user then already has an ACCESS account
    // and is registered.
    $loginServerName = env("OIDC_CLAIM_idp_name");

    if($loginServerName == "ACCESS") {
      // TODO Replace with proper redirect.
      $this->redirect("https://www.google.com");
    }

    // If the email asserted by the IdP is known then
    // most likely the user already has an ACCESS account
    // and just needs to recover it.
    $email = env("OIDC_CLAIM_email");

    $args = array();
    $args['conditions']['EmailAddress.mail'] = $email;
    $args['contain'] = false;

    $emailAddress = $this->CoPetition->EnrolleeCoPerson->EmailAddress->find('first', $args);

    if($emailAddress) {
      // TODO Replace with proper redirect.
      $this->redirect("https://www.google.com");
    }

    $this->redirect($onFinish);
  }

  /**
   * Validate POST data from an add action.
   *
   * @return Array of validated data ready for saving or false if not validated.
   */

  private function validatePost() {
    $data = $this->request->data;

    // Validate the Access01Petition fields.
    $petitionModel = new Access01Petition();
    $petitionModel->clear();
    $petitionData = array();
    $petitionData['Access01Petition'] = $data['Access01Petition'];
    $petitionModel->set($data);

    $fields = array();
    $fields[] = 'co_petition_id';
    $fields[] = 'access_organization_id';

    $args = array();
    $args['fieldList'] = $fields;

    if(!$petitionModel->validates($args)) {
      $this->Flash->set(_txt('er.fields'), array('key' => 'error'));
      return false;
    }

    return $data;
  }
}
