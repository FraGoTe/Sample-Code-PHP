<?php

class Model_Soap {

    // the auth status
    protected $authenticated = false;
    // the authenticated user
    protected $user;

    /**
     * The Authentication Method
     *
     * @param string $user The username
     * @param string $pass The Password
     * @return string $success The result.
     * @throws Exception
     */
    public function Authenticate($username, $password) {

        // we need values here
        if (empty($username) || empty($password)) {
            throw new SoapFault('Client.Authentication', 'Authentication failure. Empty username and/or password.');
        }

        // attempt to authenticate
        try {
            // the auth object
            $this->user = Model_Auth::Authenticate($username, $password, 1);
        } catch (Exception $e) {
            throw new SoapFault('Client.Authentication', $e->getMessage());
        }

        // set the auth bool
        $this->authenticated = true;

        return (string) 'Authentication Successful.';
    }

    /**
     * The private check_auth method, simply determines auth state.
     *
     * @return boolean $auth The auth status.
     * @throws SoapFault
     */
    private function check_auth() {

        // are we authenticated?
        if ($this->authenticated === true) {
            return true;
        } else {
            throw new SoapFault('Client.Authentication', 'You must authenticate before performing this action.');
        }
    }

    /**
     * Retrieve policy data by policy number.
     *
     * @param string $policy The policy number.
     * @return string $xml The Job Data.
     * @throws Exception
     */
    public function GetPolicyByNumber(string $number) {

        // check auth
        $this->check_auth();

        // we must be given a policy number
        if (empty($number)) {
            throw new SoapFault('Client', 'No policy number provided.');
        }

        // attempt to load the job
        $data = $this->GetData('policy', $number);

        // if there is no data, we are not able to find the policy
        if ($data === false) {
            throw new SoapFault('Client', 'No policy found with that number.');
        }

        return (string) $data['data'];
    }

    /**
     * Retrieve policy data by job number.
     * )
     * @param int $id The job id.
     * @return string $xml The Job Data.
     * @throws Exception
     */
    public function GetPolicyByJob(int $id) {

        // check auth
        $this->check_auth();

        // we must be given a policy number
        if (empty($id)) {
            throw new SoapFault('Client', 'Invalid Job number provided.');
        }

        // attempt to load the job
        $data = $this->GetData('job', $id);

        // if there is no data, we are not able to find the policy
        if ($data === false) {
            throw new SoapFault('Server', 'No job found with that id.');
        }

        return (string) $data['data'];
    }

    /**
     * Retrieve Eform Definition by Id
     *
     * @param int $id The Eform ID.
     * @param int $version The Eform version.
     * @return string $definition The Eform XML Definition.
     */
    public function GetEformXML($id, $version) {
        // check auth
        $this->check_auth();

        // attempt to load the Eform
        try {
            $eform = new Model_EForm($id, $this->user->GetClient()->GetId(), $version);
        } catch (Exception $e) {
            throw new SoapFault('Server', $e->getMessage());
        }

        // if we are here, return the eform XML
        //return (string) $eform->GetEformXML();
        return new SoapVar($eform->GetEformXML(), XSD_ANYXML);
    }

    /**
     * Submit Eform XML as Job Data.
     *
     * @param string $xml The Eform XML as incoming Job Data
     * @return array $result The result, either success message and Job ID or error messages
     */
    public function SubmitJobData($xml) {

        // check auth
        $this->check_auth();

        // we are going to need the client ID
        $client = $this->user->GetClient();

        // attempt to parse the XML
        try{
            $eformXmlObj = new SimpleXMLElement($xml);
        }catch(Exception $e){
            throw new SoapFault('Server.Error', $e->getMessage());
        }

        // get the eform id
        $eformId = (int) $eformXmlObj['id'];

        // get the eform version
        $eformVersion = (int) $eformXmlObj['version'];

        // Get the eform obj
        $eform = new Model_EForm($eformId, $client->GetId(), $eformVersion);

        // Create the job
        $job = Model_Job::Create($eform, $this->user, $client->GetId());

        // this is a remote job
        $job->IsRemote(true);

        // the form attributes
        $eformAttributes = current($eformXmlObj->attributes());

        // for each form
        foreach ($eformXmlObj->page as $page) {

            // the page attributes
            $pageAttributes = $page->attributes();

            // the form version
            $formVersion = (string) $eformAttributes['version'];

            // page num as string type
            $pageNum = (string) $pageAttributes['number'];

            // form id as string
            $formId = $eform->GetFormForPage($pageNum)->GetId();

            // form id/version
            $formIdVersion = $formId . '_' . $formVersion;

            // add this form to the artificial includes array
            if (!in_array($formIdVersion, $includes)) {

                // if this form isnt in the array, add it
                $includes[] = $formIdVersion;
            }

            // loop through the pages
            foreach ($page->field as $field) {

                // is there field data?
                if ($fieldValue = (string) $field) {

                    // set it
                    $job->SetValue($formId, $pageNum, (string) $field['name'], $fieldValue);
                }
            }
        }

        // set the includes
        $job->SetInclude(implode(',', $includes));

        // save the job
        $job->Save();

        // attempt to finalize the job
        return $job->Finalize(true);
    }

    /**
     * The DB Routine.
     *
     * @param string $type The type of lookup (job Id, policy #, etc.)
     * @param string $attr The attribute to search by.
     * @return array() $data The job data
     */
    private function GetData($type, $attr) {

        // Get the database connection
        $db = Zend_Registry::get('db');

        // the client id, used by some/all methods
        $client = (int) $this->user->GetClient()->GetId();

        // build the query according to the type
        switch ($type) {
            case 'job':
                $sql = $db->select()
                        ->from('job')
                        ->where('`id` = ?', $attr)
                        ->where('`client` = ?', $client);
                break;
            case 'policy':
                $sql = $db->select()
                        ->from('job')
                        ->where('`index1` = ?', $attr)
                        ->where('`client` = ?', $client);
                break;
            default:
                throw new SoapFault('Client', 'That is not a valid function request.');
                break;
        }

        // run query
        $query = $db->query($sql);

        // do we have a result?
        if ($query->rowCount() == 0) {
            return false;
        }

        // Get the row
        $row = $query->fetch();

        // return
        return $row;
    }

}
