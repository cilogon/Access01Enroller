This COmanage Registry enrollment plugin is intended to be used
with a self-signup enrollment flow for ACCESS that requires
authentication with a federated identity to begin the flow.

The following enrollment steps are implemented:

finalize:
   - Used to add OrgIdentity with ACCESS CI ePPN as
     login identifier, and an ePPN and OIDC sub
     with the form <ACCESS ID>@access-ci.org on the
     CO Person record.

 petitionerAttributes:
   - Used to collect the ACCESS Organization for the
     user from a controlled set.

 provision:
   - Used to ask if the user wants to set a Kerberos
     password for their new ACCESS ID and if so
     sets the password in the KDC.

 start:
   - Used to detect if the ACCESS IdP was used for
     authentication and redirect, or if the email
     asserted by the IdP is already known and attached
     to a registered user.
