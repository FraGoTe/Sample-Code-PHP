<?php
/**
 * Eform SOAP Controller
 */
class SoapController extends Zend_Controller_Action{

    // the WSDL URL
    protected $wsdl;

    // the options array
    protected $authOptions = array(
        'accept_schemes' => 'basic',
        'realm'          => 'EformSOAP'
        );

    // the soap options
    protected $soapOptions = array(
        'classmap'       => array('Model_Soap'),
        'encoding'       => 'UTF-8',
        'soap_version'   => SOAP_1_2,
        'cache_wsdl'     => WSDL_CACHE_NONE,
    );

    /**
     * For all actions, disable layout and view rendering
     */
    public function init(){

        // disable layout/output
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);

        // the wsdl address
        $this->wsdl = $this->view->serverUrl() . $this->_helper->url('wsdl','soap');
    }

    /**
     * The index, used for serving requests.
     *
     * @return void
     */
    public function indexAction(){

        // init the soap sever
        $server = new SoapServer($this->wsdl, $this->soapOptions);

        // bind our web service class 
        $server->setClass('Model_Soap');

        // handle the request
        $server->handle();
    }

    /**
     * Auto-Discover, generates the WSDL
     *
     * @return void
     */
    public function wsdlAction(){

        // init auto discover
        $autodiscover = new Zend_Soap_AutoDiscover();

        // set the class
        $autodiscover->setClass('Model_Soap', '', null, $_SERVER['PHP_SELF']);

        // we are not using the standard uri detection
        $autodiscover->setUri($this->view->serverUrl() . $this->_helper->url(null));

        // handle the wsdl request
        $autodiscover->handle();
    }

    /**
     * The HTTP Authentication Method
     * NOTE: We are not using this method anymore, we are using the Eform auth method.
     *
     * @return bool
     */
    protected function Authenticate(){

        // init the auth adapter
        $adapter = new Zend_Auth_Adapter_Http($this->authOptions);

        // the pwd file
        $pwdFile = realpath(APPLICATION_PATH . '/../../pwd/basicPasswd.txt');

        // config the basic auth resolver by file
        $basicResolver = new Zend_Auth_Adapter_Http_Resolver_File($pwdFile);
        $adapter->setBasicResolver($basicResolver);

        // configure the adapters request/response
        $adapter->setRequest($this->getRequest());
        $adapter->setResponse($this->getResponse());

        // attempt to authenticate using basic HTTP Auth
        $result = $adapter->authenticate();

        // is valid?
        if(!$result->isValid()){
            return false;
        }else{
            return true;
        }
    }
}

