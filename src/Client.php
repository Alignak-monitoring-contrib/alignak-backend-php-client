<?php

include(dirname(__DIR__).'/vendor/rmccue/requests/library/Requests.php');

class Alignak_Backend_Client {

    private $connected = false;

    private $authenticated = false;

    private $processes = 1;

    private $url_endpoint_root = '';

    public $token = NULL;

    /**
     * Initiate configuration
     *
     * @param type $endpoint root endpoint (API URL)
     * @param type $processes Number of processes used by GET
     */
    public function __construct($endpoint, $processes=1) {
        $this->$processes = $processes;
        if (substr($endpoint, -1) == '/') {
            $this->url_endpoint_root = substr($endpoint, 0, -1);
        } else {
            $this->url_endpoint_root = $endpoint;
        }
        Requests::register_autoloader();
    }

    /**
     * Log into the backend and get the token
     *
     *   generate parameter may have following values:
     *   - enabled: require current token (default)
     *   - force: force new token generation
     *   - disabled
     *
     *   if login is:
     *   - accepted, returns True
     *   - refused, returns False
     *
     *   In case of any error, raises a BackendException
     *
     *
     * @param type $username login name
     * @param type $password password
     * @param type $generate Can have these values: enabled | force | disabled
     */
    function login($username, $password, $generate='enabled') {

        ///logger.info("request backend authentication for: %s, generate: %s", username, generate)

        ///if (empty($username) OR empty($password))
        ///   raise BackendException(1001, "Missing mandatory parameters")

        $this->authenticated = false;
        $this->token = NULL;

        $headers = array('Content-Type' => 'application/json');
        $params = array('name' => $username, 'password' => $password);
        if ($generate == 'force') {
            $params['action'] = 'generate';
        }

        $response = Requests::post($this->url_endpoint_root.'/login', $headers,
                json_encode($params));
        if ($response->status_code == 401) {
            return false;
        }
        /***
            response.raise_for_status()
        except Timeout as e:  # pragma: no cover - need specific backend tests
            logger.error("Backend connection timeout, error: %s", str(e))
            raise BackendException(1002, "Backend connection timeout")
        except HTTPError as e:  # pragma: no cover - need specific backend tests
            logger.error("Backend HTTP error, error: %s", str(e))
            raise BackendException(1003, "Backend HTTPError: %s / %s" % (type(e), str(e)))
        except Exception as e:  # pragma: no cover - security ...
            logger.error("Backend connection exception, error: %s / %s", type(e), str(e))
            raise BackendException(1000, "Backend exception: %s / %s" % (type(e), str(e)))
        ***/
        $resp = json_decode($response->body, true);
        ///logger.debug("authentication response: %s", resp)

        if (isset($resp['_status'])) {
            // Considering an information is returned if a _status field is present ...
            ///logger.warning("backend status: %s", resp['_status'])
            echo $resp['_status'];
        }

        if (isset($resp['_error'])) {
            // Considering a problem occured is an _error field is present ...
            $error = $resp['_error'];
            print $error;
            ///logger.error(
            ///    "authentication, error: %s, %s",
            ///    $error['code'], $error['message']
            ///)
            ///raise BackendException(error['code'], error['message'])
        } else {
            if (isset($resp['token'])) {
                $this->token = $resp['token'];
                $this->authenticated = true;
                ///logger.info("user authenticated: %s", username)
                return true;
            } else if ($generate == 'force') {
                echo "Token generation required but none provided.";
                ///logger.error("Token generation required but none provided.")
                ///raise BackendException(1004, "Token not provided")
            } else if ($generate == 'disabled') {
                echo "Token disabled ... to be implemented!";
                ///logger.error("Token disabled ... to be implemented!")
                return false;
            } else if ($generate == 'enabled') {
                echo "Token enabled, but none provided, require new token generation";
                //logger.warning("Token enabled, but none provided, require new token generation")
                return $this->login($username, $password, 'force');
            }
            return false;
        }
    }

    function logout() {
        // TODO
        $this->authenticated = false;
        $this->token = NULL;

        return true;
    }

    /**
     *  Connect to alignak backend and retrieve all available child endpoints of root

     *  If connection is successfull, returns a list of all the resources available in the backend:
     *  Each resource is identified with its title and provides its endpoint relative to backend
     *  root endpoint.
     *      [
     *          {u'href': u'loghost', u'title': u'loghost'},
     *          {u'href': u'escalation', u'title': u'escalation'},
     *          ...
     *      ]
     *
     *  If an error occurs a BackendException is raised.
     *
     *  If an exception occurs, it is raised to caller.
     *
     */
    function get_domains() {
        if (is_null($this->token)) {
            ///logger.error("Authentication is required for getting an object.")
            ///raise BackendException(1001, "Access denied, please login before trying to get")
        }

        ///logger.debug("trying to get domains from backend: %s", self.url_endpoint_root)

        $resp = $this->get('');
        ///logger.debug("received domains data: %s", resp)
        if (isset($resp["_links"])) {
            $_links = $resp["_links"];
            if (isset($_links["child"])) {
                return $_links["child"];
            }
        }
        return array();
    }

    /**
     *  Get items or item in alignak backend
     *
     *  If an error occurs, a BackendException is raised.
     *
     * @param type $endpoint endpoint (API URL) relative from root endpoint
     * @param type $params list of parameters for the backend API
     */
    function get($endpoint, $params=array()) {
        if (is_null($this->token)) {
            ///logger.error("Authentication is required for getting an object.")
            ///raise BackendException(1001, "Access denied, please login before trying to get")
        }
        ///logger.debug("get, endpoint: %s, parameters: %s", endpoint, params)

        $params['auth'] = array($this->token, '');
        $response = Requests::get($this->url_endpoint_root.'/'.$endpoint,
                array(),
                $params);

        $resp = json_decode($response->body, true);

        if (isset($resp['_status'])) {
            // Considering an information is returned if a _status field is present ...
            ///logger.warning("backend status: %s", resp['_status'])
        }

        if (isset($resp['_error'])) {
            // Considering a problem occured is an _error field is present ...
            $error = $resp['_error'];
            //logger.error("backend error: %s, %s", error['code'], error['message'])
            //raise BackendException(error['code'], error['message'])
        }
        // logger.debug("get, endpoint: %s, response: %s", endpoint, resp)

        return $resp;
    }

    function get_all($endpoint, $params=array()) {
        // TODO
    }


    /**
     * Create a new item
     *
     * @param type $endpoint endpoint (API URL)
     * @param type $data properties of item to create
     * @param type $headers headers (example: Content-Type)
     */
    function post($endpoint, $data, $headers=array()) {
        if (is_null($this->token)) {
            ///logger.error("Authentication is required for adding an object.")
            ///raise BackendException(1001, "Access denied, please login before trying to post")
        }

        if (empty($headers)) {
            $headers = array('Content-Type' => 'application/json');
        }
        $auth = array('auth' => array($this->token, ''));

        $response = Requests::post($this->url_endpoint_root.'/'.$endpoint,
                $headers,
                json_encode($data),
                $auth);
        $resp = json_decode($response->body, true);
        ///try
        ///    resp = response.json()
        ///except Exception:
        ///    resp = response
        ///    logger.error(
        ///        "Response is not JSON formatted: %d / %s", response.status_code, response.content
        ///    )
        ///    raise BackendException(
        ///        1003,
        ///        "Response is not JSON formatted: %d / %s" % (
        ///            response.status_code, response.content
        ///        ),
        ///        response
        ///    )

        if (isset($resp['_status'])) {
            // Considering an information is returned if a _status field is present ...
            ///logger.warning("backend status: %s", resp['_status'])
        }

        if (isset($resp['_error'])) {
            // Considering a problem occured is an _error field is present ...
            $error = $resp['_error'];
            ///logger.error("backend error: %s, %s", error['code'], error['message'])
            if (isset($resp['_issues'])) {
                foreach($resp['_issues'] as $issue) {
                    //logger.error(" - issue: %s: %s", issue, resp['_issues'][issue])
                }
            }
            ///raise BackendException(error['code'], error['message'], resp)
        }
        return $resp;
    }
}