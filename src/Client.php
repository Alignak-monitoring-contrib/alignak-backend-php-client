<?php

include(dirname(__DIR__).'/vendor/rmccue/requests/library/Requests.php');

class Alignak_Backend_Client {

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
        $this->processes = $processes;
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

        if (empty($username) OR empty($password)) {
            throw new Exception('Missing mandatory parameters', 1001);
        }

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
            throw new Exception($error['message'], $error['code']);
        } else {
            if (isset($resp['token'])) {
                $this->token = $resp['token'];
                $this->authenticated = true;
                ///logger.info("user authenticated: %s", username)
                return true;
            } else if ($generate == 'force') {
                echo "Token generation required but none provided.";
                ///logger.error("Token generation required but none provided.")
                throw new Exception("Token not provided", 1004);
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
            throw new Exception("Access denied, please login before trying to get", 1001);
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
            throw new Exception("Access denied, please login before trying to get", 1001);
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
            throw new Exception($error['message'], $error['code']);
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
            throw new Exception("Access denied, please login before trying to get", 1001);
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
            throw new Exception($error['message'], $error['code']);
        }
        return $resp;
    }

    /**
     * Method to update an item
     *
     *  The headers must include an If-Match containing the object _etag.
     *      headers = {'If-Match': contact_etag}
     *
     *  The data dictionary contain the fields that must be modified.
     *
     *  If the patching fails because the _etag object do not match with the provided one, a
     *  BackendException is raised with code = 412.
     *
     *  If inception is True, this method makes e new get request on the endpoint to refresh the
     *  _etag and then a new patch is called.
     *
     *  If an HTTP 412 error occurs, a BackendException is raised. This exception is:
     *  - code: 412
     *  - message: response content
     *  - response: backend response
     *
     *  All other HTTP error raises a BackendException.
     *  If some _issues are provided by the backend, this exception is:
     *  - code: HTTP error code
     *  - message: response content
     *  - response: JSON encoded backend response (including '_issues' dictionary ...)
     *
     *  If no _issues are provided and an _error is signaled by the backend, this exception is:
     *  - code: backend error code
     *  - message: backend error message
     *  - response: JSON encoded backend response
     *
     * @param type $endpoint endpoint (API URL)
     * @param type $data properties of item to update
     * @param type $headers headers (example: Content-Type). 'If-Match' required
     * @param type $inception if true tries to get the last _etag
     */
    function patch($endpoint, $data, $headers=array(), $inception=false) {

        if (is_null($this->token)) {
            ///logger.error("Authentication is required for patching an object.")
            throw new Exception("Access denied, please login before trying to get", 1001);
        }
        if (empty($headers)) {
            ///logger.error("Header If-Match is required for patching an object.")
            ///raise BackendException(1005, "Header If-Match required for patching an object")
        }

        $auth = array('auth' => array($this->token, ''));

        $headers['Content-Type'] = 'application/json';

        $response = Requests::patch($this->url_endpoint_root.'/'.$endpoint,
                $headers,
                json_encode($data),
                $auth);

        if ($response->status_code == 200) {
           return json_decode($response->body, true);
        } else if ($response->status_code == 412) {
            if ($inception) {
                $resp = $this->get($endpoint);
                $headers['If-Match'] = $resp['_etag'];
                return $this->patch($endpoint, $data, $headers);
            } else {
                throw new Exception($response->content, 412);
            }
        } else {
            ///logger.error(
            ///    "Patching failed, response is: %d / %s",
            ///    response.status_code, response.content
            ///)
            $resp = json_decode($response->body, true);
            if (isset($resp['_status'])) {
                // Considering an information is returned if a _status field is present ...
                ///logger.warning("backend status: %s", resp['_status'])
            }
            if (isset($resp['_issues'])) {
                foreach($resp['_issues'] as $issue) {
                    ///logger.error(" - issue: %s: %s", issue, resp['_issues'][issue])
                }
                throw new Exception($response->content, $response->status_code);
            }
            if (isset($resp['_error'])) {
                // Considering a problem occured if an _error field is present ...
                $error = $resp['_error'];
                ///logger.error("backend error: %s, %s", error['code'], error['message'])
                throw new Exception($error['message'], $error['code']);
            }
            return $resp;
        }
    }

    /**
     * Method to delete an item or all items
     *
     *  headers['If-Match'] must contain the _etag identifier of the element to delete
     *
     * @param type $endpoint endpoint (API URL)
     * @param type $headers headers (example: Content-Type)
     */
    function delete($endpoint, $headers) {
        if (is_null($this->token)) {
            ///logger.error("Authentication is required for deleting an object.")
            throw new Exception("Access denied, please login before trying to get", 1001);
        }
        $data = array('auth' => array($this->token, ''));

        $response = Requests::delete($this->url_endpoint_root.'/'.$endpoint,
                $headers,
                $data);
        if ($response->status_code != 204) {
            ///response.raise_for_status()
        }

        ///except Timeout as e:  # pragma: no cover - need specific backend tests
        ///    logger.error("Backend connection timeout, error: %s", str(e))
        ///    raise BackendException(1002, "Backend connection timeout")
        ///except HTTPError as e:  # pragma: no cover - need specific backend tests
        ///    logger.error("Backend HTTP error, error: %s", str(e))
        ///    raise BackendException(1003, "Backend HTTPError: %s / %s" % (type(e), str(e)))
        ///except Exception as e:  # pragma: no cover - security ...
        ///    logger.error("Backend connection exception, error: %s / %s", type(e), str(e))
        ///    raise BackendException(1000, "Backend exception: %s / %s" % (type(e), str(e)))

        return array();
    }
}